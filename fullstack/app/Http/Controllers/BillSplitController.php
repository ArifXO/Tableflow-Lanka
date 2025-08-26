<?php

namespace App\Http\Controllers;

use App\Models\BillSplit;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillSplitController extends Controller
{
    public function apiShow(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load('orderItems.dish');
        $split = BillSplit::where('order_id',$order->id)->first();
        return response()->json([
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'total_amount' => (float)$order->total_amount,
                'items' => $order->orderItems->map(fn($i) => [
                    'id' => $i->id,
                    'name' => $i->dish->name_en ?? $i->dish->name_bn ?? 'Item',
                    'quantity' => $i->quantity,
                    'price' => (float)$i->price,
                    'total' => (float)($i->price * $i->quantity)
                ])
            ],
            'billSplit' => $split ? [
                'id' => $split->id,
                'tip_amount' => (float)$split->tip_amount,
                'total_before_tip' => (float)$split->total_before_tip,
                'total_after_tip' => (float)$split->total_after_tip,
                'participants' => collect($split->participants)->map(fn($p, $idx) => [
                    'id' => $idx+1,
                    'name' => $p['name'],
                    'share_before_tip' => $p['share_before_tip'],
                    'share_tip' => $p['share_tip'],
                    'share_total' => $p['share_total'],
                    'paid' => $p['paid'] ?? false,
                    'items' => $p['items'] ?? []
                ])
            ] : null
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $this->authorizeOrder($order);
        $data = $request->validate([
            'participants' => 'required|array|min:1',
            'participants.*.name' => 'required|string|max:100',
            'participants.*.items' => 'required|array|min:1', // each entry: int id OR ['id'=>int,'quantity'=>int]
            'tip_percent' => 'nullable|numeric|min:0|max:100',
            'tip_amount' => 'nullable|numeric|min:0'
        ]);

        $order->load('orderItems');
        $items = $order->orderItems->keyBy('id');
        // Build quantity allocation per participant per item
        $allocations = []; // [itemId][participantIndex] = quantity
        foreach ($data['participants'] as $pIdx => $p) {
            foreach ($p['items'] as $raw) {
                // Accept integer (means full item) or object {id, quantity}
                if (is_int($raw)) { $itemId = $raw; $qty = $items->get($itemId)?->quantity ?? 0; }
                else { $itemId = $raw['id'] ?? null; $qty = (int)($raw['quantity'] ?? 0); }
                if(!$itemId || !$items->has($itemId) || $qty <= 0) continue;
                $allocations[$itemId][$pIdx] = ($allocations[$itemId][$pIdx] ?? 0) + min($qty, $items[$itemId]->quantity);
            }
        }

        // Validate total allocated quantity does not exceed item quantity; clamp if needed
        foreach ($allocations as $itemId => &$byParticipant) {
            $allowed = $items[$itemId]->quantity;
            $used = array_sum($byParticipant);
            if ($used > $allowed) {
                // Scale down proportionally
                foreach ($byParticipant as $pIdx => $q) {
                    $byParticipant[$pIdx] = (int) floor($q * ($allowed / $used));
                }
                // Adjust remainder to first participant if any leftover
                $diff = $allowed - array_sum($byParticipant);
                if ($diff > 0) { $firstKey = array_key_first($byParticipant); $byParticipant[$firstKey] += $diff; }
            }
        }
        unset($byParticipant);

        $base = array_fill(0, count($data['participants']), 0.0);
        $totalBefore = 0.0;
        foreach ($items as $itemId => $item) {
            $pricePerUnit = (float)$item->price;
            $totalBefore += $pricePerUnit * $item->quantity;
            if (!empty($allocations[$itemId])) {
                foreach ($allocations[$itemId] as $pIdx => $qty) {
                    $base[$pIdx] += $pricePerUnit * $qty;
                }
            } else {
                // Unallocated item -> assign to first participant
                $base[0] += $pricePerUnit * $item->quantity;
            }
        }

        $tip = 0.0;
        if (!empty($data['tip_amount'])) $tip = (float)$data['tip_amount'];
        elseif (!empty($data['tip_percent'])) $tip = round($totalBefore * ((float)$data['tip_percent']/100), 2);

        $participantsOut = [];
        foreach ($data['participants'] as $idx => $p) {
            $shareBefore = round($base[$idx], 2);
            $shareTip = $totalBefore>0 ? round($tip * ($shareBefore/$totalBefore), 2) : 0;
            $shareTotal = round($shareBefore + $shareTip, 2);
            // Build items list with quantities for this participant
            $participantItems = [];
            foreach ($allocations as $itemId => $byP) {
                if (!empty($byP[$idx])) {
                    $participantItems[] = ['id'=>$itemId,'quantity'=>$byP[$idx]];
                }
            }
            $participantsOut[] = [
                'name' => $p['name'],
                'items' => $participantItems,
                'share_before_tip' => $shareBefore,
                'share_tip' => $shareTip,
                'share_total' => $shareTotal,
                'paid' => false,
            ];
        }

        BillSplit::where('order_id',$order->id)->delete();
        $split = BillSplit::create([
            'order_id' => $order->id,
            'participants' => $participantsOut,
            'total_before_tip' => $totalBefore,
            'tip_amount' => $tip,
            'total_after_tip' => $totalBefore + $tip,
        ]);

        return response()->json([
            'message' => 'Bill split saved',
            'bill_split_id' => $split->id,
        ], 201);
    }

    protected function authorizeOrder(Order $order): void
    {
        if ($order->user_id !== Auth::id()) abort(403);
        if ($order->status === 'cancelled') abort(400,'Cannot split cancelled order');
    }
}
    // Temporary debug logging
