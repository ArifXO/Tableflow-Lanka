<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagerOrderController extends Controller
{
    protected function authorizeManager(): void
    {
        $user = Auth::user();
        if (!$user || !$user->isManager()) abort(403);
    }

    /**
     * List orders with payments & items for manager view.
     */
    public function index(Request $request)
    {
        $this->authorizeManager();
        $status = $request->query('status');
        $q = Order::with(['user','payments','orderItems.dish'])
            ->when($status, fn($query) => $query->where('status',$status))
            ->orderByDesc('created_at');
        $orders = $q->get()->map(function(Order $o){
            $paidTotal = (float) $o->payments->whereIn('status',['paid','confirmed'])->sum('amount');
            $confirmedTotal = (float) $o->payments->where('status','confirmed')->sum('amount');
            return [
                'id' => $o->id,
                'user' => [ 'id' => $o->user?->id, 'name' => $o->user?->name ],
                'status' => $o->status,
                'order_date' => $o->order_date?->toDateTimeString(),
                'total_amount' => (float)$o->total_amount,
                'paid_total' => $paidTotal,
                'confirmed_total' => $confirmedTotal,
                'is_fully_confirmed' => $confirmedTotal >= (float)$o->total_amount,
                'payments' => $o->payments->map(fn($p)=>[
                    'id'=>$p->id,
                    'amount'=>(float)$p->amount,
                    'tip_amount'=>(float)$p->tip_amount,
                    'method'=>$p->method,
                    'status'=>$p->status,
                    'created_at'=>$p->created_at?->toDateTimeString(),
                ])->values(),
                'items' => $o->orderItems->map(fn($i)=>[
                    'id'=>$i->id,
                    'dish_name'=>$i->dish?->name_en ?? $i->dish?->name_bn ?? 'Item',
                    'quantity'=>$i->quantity,
                    'price'=>(float)$i->price,
                ])->values()
            ];
        });

        return response()->json(['orders'=>$orders]);
    }

    /**
     * Confirm a payment and award loyalty points if order fully confirmed.
     */
    public function confirmPayment(Payment $payment)
    {
        $this->authorizeManager();
        if ($payment->status === 'confirmed') {
            return response()->json(['message'=>'Already confirmed','payment'=>$payment],200);
        }
        $payment->update(['status'=>'confirmed']);
        $order = $payment->order()->with('payments','user')->first();
        $confirmedTotal = (float)$order->payments->where('status','confirmed')->sum('amount');
        if ($confirmedTotal >= (float)$order->total_amount) {
            $order->awardLoyaltyIfNeeded();
        }
        return response()->json(['message'=>'Payment confirmed','payment'=>$payment->fresh(),'order_id'=>$order->id]);
    }
}
