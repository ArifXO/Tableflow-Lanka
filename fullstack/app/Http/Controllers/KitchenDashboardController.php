<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class KitchenDashboardController extends Controller
{
    public function __invoke()
    {
        // Show orders that are pending or preparing
        $orders = Order::with(['orderItems.dish', 'user'])
            ->whereIn('status', ['pending', 'preparing'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($o){
                return [
                    'id' => $o->id,
                    'status' => $o->status,
                    'created_at' => $o->created_at,
                    'user' => ['id'=>$o->user?->id,'name'=>$o->user?->name],
                    'order_items' => $o->orderItems->map(fn($i)=> [
                        'id'=>$i->id,
                        'quantity'=>$i->quantity,
                        'dish'=>[
                            'id'=>$i->dish?->id,
                            'name_bn'=>$i->dish?->name_bn,
                            'name_en'=>$i->dish?->name_en,
                            'name'=>$i->dish?->name_bn ?? $i->dish?->name_en,
                        ]
                    ])
                ];
            });

        return Inertia::render('KitchenDashboard', [
            'orders' => $orders,
            'stats' => [
                'pending' => Order::where('status', 'pending')->count(),
                'preparing' => Order::where('status', 'preparing')->count(),
                'ready' => Order::where('status', 'ready')->count(),
            ],
            'user' => Auth::user(),
        ]);
    }
}
