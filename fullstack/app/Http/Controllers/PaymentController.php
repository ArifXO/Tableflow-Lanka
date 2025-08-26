<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\BillSplit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) abort(403);

        // Prevent duplicate payment attempts once any payment exists
        if ($order->payments()->exists()) {
            return response()->json([
                'message' => 'Payment already initiated for this order.',
                'payment' => $order->payments()->latest()->first()
            ], 409);
        }

        $data = $request->validate([
            'method' => 'required|string|in:cash,card,wallet',
            'amount' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0'
        ]);

        $split = BillSplit::where('order_id',$order->id)->first();
        $tip = $data['tip_amount'] ?? ($split? (float)$split->tip_amount : 0.0);
        $amount = $data['amount'] ?? (float)$order->total_amount + $tip;

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $amount,
            'tip_amount' => $tip,
            'method' => $data['method'],
            'status' => 'paid'
        ]);

    return response()->json(['message' => 'Payment recorded', 'payment' => $payment], 201);
    }
}
