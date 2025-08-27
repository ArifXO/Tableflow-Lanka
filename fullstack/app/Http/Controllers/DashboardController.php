<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    // Display the dashboard with user's orders and reservations history
    public function index()
    {
        $user = Auth::user();

        // Get user's recent orders with order items and dishes
    $orders = Order::with(['orderItems.dish','payments'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get user's recent reservations with table information
        $reservations = Reservation::with('table')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Transform orders to include payment summary
        $ordersOut = $orders->map(function($o){
            $firstPayment = $o->payments->first();
            $confirmedTotal = (float)$o->payments->where('status','confirmed')->sum('amount');
            return [
                'id'=>$o->id,
                'total_amount'=>(float)$o->total_amount,
                'status'=>$o->status,
                'order_date'=>$o->order_date,
                'created_at'=>$o->created_at,
                'order_items'=>$o->orderItems->map(fn($i)=>[
                    'id'=>$i->id,
                    'quantity'=>$i->quantity,
                    'price'=>(float)$i->price,
                    'dish'=>[
                        'id'=>$i->dish?->id,
                        'bn'=>$i->dish?->name_bn,
                        'en'=>$i->dish?->name_en,
                        'price'=>$i->dish?->price,
                    ]
                ]),
                'payment'=> $firstPayment ? [
                    'id'=>$firstPayment->id,
                    'status'=>$firstPayment->status,
                    'method'=>$firstPayment->method,
                    'amount'=>(float)$firstPayment->amount,
                ] : null,
                'confirmed_total'=>$confirmedTotal,
                'is_fully_confirmed'=>$confirmedTotal >= (float)$o->total_amount,
            ];
        });

        return Inertia::render('Dashboard', [
            'orders' => $ordersOut,
            'reservations' => $reservations,
            'stats' => [
                'total_orders' => Order::where('user_id', $user->id)->count(),
                'total_reservations' => Reservation::where('user_id', $user->id)->count(),
                'pending_orders' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
                'confirmed_reservations' => Reservation::where('user_id', $user->id)->where('status', 'confirmed')->count(),
                'loyalty_points' => $user->getLoyaltyPoints(),
                'completed_orders' => Order::where('user_id', $user->id)->where('status', 'delivered')->count(),
            ]
        ]);
    }

    // Lightweight JSON endpoint for polling recent orders & stats (for real-time dashboard updates)
    public function ordersApi()
    {
        $user = Auth::user();
        $orders = Order::with(['orderItems.dish','payments'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $ordersOut = $orders->map(function($o){
            $firstPayment = $o->payments->first();
            $confirmedTotal = (float)$o->payments->where('status','confirmed')->sum('amount');
            return [
                'id'=>$o->id,
                'total_amount'=>(float)$o->total_amount,
                'status'=>$o->status,
                'created_at'=>$o->created_at,
                'order_items'=>$o->orderItems->map(fn($i)=>[
                    'id'=>$i->id,
                    'quantity'=>$i->quantity,
                    'price'=>(float)$i->price,
                    'dish'=>[
                        'id'=>$i->dish?->id,
                        'bn'=>$i->dish?->name_bn,
                        'en'=>$i->dish?->name_en,
                    ]
                ]),
                'payment'=> $firstPayment ? [
                    'id'=>$firstPayment->id,
                    'status'=>$firstPayment->status,
                    'method'=>$firstPayment->method,
                    'amount'=>(float)$firstPayment->amount,
                ] : null,
                'confirmed_total'=>$confirmedTotal,
                'is_fully_confirmed'=>$confirmedTotal >= (float)$o->total_amount,
            ];
        });

        return response()->json([
            'orders'=>$ordersOut,
            'stats'=>[
                'total_orders' => Order::where('user_id', $user->id)->count(),
                'pending_orders' => Order::where('user_id', $user->id)->where('status','pending')->count(),
                'completed_orders' => Order::where('user_id', $user->id)->where('status','delivered')->count(),
                'loyalty_points' => $user->getLoyaltyPoints(),
            ]
        ]);
    }
}
