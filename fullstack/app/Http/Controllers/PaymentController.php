<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'method' => 'required|string|in:cash,card,wallet',
            'amount' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
        ]);

        $amount = $data['amount'] ?? (float) $order->total_amount + (float) ($data['tip_amount'] ?? 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $amount,
            'tip_amount' => $data['tip_amount'] ?? 0,
            'method' => $data['method'],
            'status' => 'paid',
            'meta' => [ 'source' => 'manual_split' ]
        ]);

        return response()->json([
            'message' => 'Payment recorded',
            'payment' => $payment
        ], 201);
    }
}
