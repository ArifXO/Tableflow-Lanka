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
            'participants.*.items' => 'required|array|min:1',
            'participants.*.items.*' => 'integer',
            'tip_percent' => 'nullable|numeric|min:0|max:100',
            'tip_amount' => 'nullable|numeric|min:0'
        ]);

        $order->load('orderItems');
        $items = $order->orderItems->keyBy('id');
        $map = [];
        foreach ($data['participants'] as $pIdx => $p) {
            foreach ($p['items'] as $itemId) {
                if (!$items->has($itemId)) continue;
                $map[$itemId] = $map[$itemId] ?? [];
                $map[$itemId][] = $pIdx;
            }
        }

        $base = array_fill(0, count($data['participants']), 0.0);
        $totalBefore = 0.0;
        foreach ($items as $item) {
            $itemTotal = (float)$item->price * $item->quantity;
            $totalBefore += $itemTotal;
            if (!empty($map[$item->id])) {
                $portion = $itemTotal / count($map[$item->id]);
                foreach ($map[$item->id] as $pIdx) { $base[$pIdx] += $portion; }
            } else { $base[0] += $itemTotal; }
        }

        $tip = 0.0;
        if (!empty($data['tip_amount'])) $tip = (float)$data['tip_amount'];
        elseif (!empty($data['tip_percent'])) $tip = round($totalBefore * ((float)$data['tip_percent']/100), 2);

        $participantsOut = [];
        foreach ($data['participants'] as $idx => $p) {
            $shareBefore = round($base[$idx], 2);
            $shareTip = $totalBefore>0 ? round($tip * ($shareBefore/$totalBefore), 2) : 0;
            $shareTotal = round($shareBefore + $shareTip, 2);
            $participantsOut[] = [
                'name' => $p['name'],
                'items' => $p['items'],
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
