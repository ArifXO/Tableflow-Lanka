<?php

namespace App\Http\Controllers;

use App\Models\BillSplit;
use App\Models\BillSplitParticipant;
use App\Models\BillSplitItemParticipant;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillSplitController extends Controller
{
    /**
     * Redirect to bill split page for the user's most recent (non-cancelled) order.
     */

    public function apiShow(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load('orderItems.dish');
        $billSplit = BillSplit::with('participants.orderItems')
            ->where('order_id', $order->id)
            ->first();
        return response()->json([
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'items' => $order->orderItems->map(fn($i) => [
                    'id' => $i->id,
                    'name' => $i->dish->name_en ?? $i->dish->name_bn ?? 'Item',
                    'quantity' => $i->quantity,
                    'price' => $i->price,
                    'total' => $i->price * $i->quantity,
                ])
            ],
            'billSplit' => $billSplit ? [
                'id' => $billSplit->id,
                'tip_amount' => $billSplit->tip_amount,
                'total_before_tip' => $billSplit->total_before_tip,
                'total_after_tip' => $billSplit->total_after_tip,
                'participants' => $billSplit->participants->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'share_before_tip' => $p->share_before_tip,
                    'share_tip' => $p->share_tip,
                    'share_total' => $p->share_total,
                    'paid' => $p->paid,
                    'items' => $p->orderItems->pluck('id')
                ])
            ] : null
        ]);
    }

    public function createPayment(Request $request, Order $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'participants' => 'required|array|min:1',
            'participants.*.name' => 'required|string|max:100',
            'participants.*.items' => 'required|array|min:1',
            'participants.*.items.*' => 'integer|exists:order_items,id',
            'tip_percent' => 'nullable|numeric|min:0|max:100',
            'tip_amount' => 'nullable|numeric|min:0'
        ]);

        $order->load('orderItems');
        $items = $order->orderItems->keyBy('id');

        // Map each item to participants selecting it
        $itemToParticipants = [];
        foreach ($validated['participants'] as $index => $participant) {
            foreach ($participant['items'] as $itemId) {
                if (!isset($items[$itemId])) continue; // skip invalid (should be validated already)
                $itemToParticipants[$itemId] = $itemToParticipants[$itemId] ?? [];
                $itemToParticipants[$itemId][] = $index; // participant index
            }
        }

        // Compute base shares
        $baseShares = array_fill(0, count($validated['participants']), 0.0);
        $totalBeforeTip = 0.0;
        foreach ($items as $item) {
            $itemTotal = (float) ($item->price * $item->quantity);
            $totalBeforeTip += $itemTotal;
            if (!empty($itemToParticipants[$item->id])) {
                $splitCount = count($itemToParticipants[$item->id]);
                $portion = $itemTotal / $splitCount;
                foreach ($itemToParticipants[$item->id] as $pIndex) {
                    $baseShares[$pIndex] += $portion;
                }
            } else {
                // If unassigned items exist, assign full cost to first participant (fallback)
                $baseShares[0] += $itemTotal;
            }
        }

        // Determine tip
        $tipAmount = 0.0;
        if (!empty($validated['tip_amount'])) {
            $tipAmount = (float) $validated['tip_amount'];
        } elseif (!empty($validated['tip_percent'])) {
            $tipAmount = round($totalBeforeTip * ((float) $validated['tip_percent'] / 100), 2);
        }

        $totalAfterTip = $totalBeforeTip + $tipAmount;

        DB::beginTransaction();
        try {
            // Remove existing split if any (recreate)
            BillSplit::where('order_id', $order->id)->delete();

            $billSplit = BillSplit::create([
                'order_id' => $order->id,
                'total_before_tip' => $totalBeforeTip,
                'tip_amount' => $tipAmount,
                'total_after_tip' => $totalAfterTip,
            ]);

            $participantsModels = [];
            foreach ($validated['participants'] as $idx => $pData) {
                $shareBefore = round($baseShares[$idx], 2);
                $shareTip = $totalBeforeTip > 0 ? round($tipAmount * ($shareBefore / $totalBeforeTip), 2) : 0;
                $shareTotal = round($shareBefore + $shareTip, 2);
                $participantModel = BillSplitParticipant::create([
                    'bill_split_id' => $billSplit->id,
                    'name' => $pData['name'],
                    'share_before_tip' => $shareBefore,
                    'share_tip' => $shareTip,
                    'share_total' => $shareTotal,
                ]);
                $participantsModels[] = $participantModel;
            }

            // Attach item selections
            foreach ($validated['participants'] as $idx => $pData) {
                $participantModel = $participantsModels[$idx];
                foreach ($pData['items'] as $itemId) {
                    if (isset($items[$itemId])) {
                        BillSplitItemParticipant::create([
                            'bill_split_id' => $billSplit->id,
                            'participant_id' => $participantModel->id,
                            'order_item_id' => $itemId,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Bill split created',
                'bill_split_id' => $billSplit->id,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create bill split',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function authorizeOrder(Order $order): void
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        if ($order->isCancelled()) {
            abort(400, 'Cannot split a cancelled order');
        }
    }
}
