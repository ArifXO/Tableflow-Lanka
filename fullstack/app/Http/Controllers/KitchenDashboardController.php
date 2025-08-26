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
            ->get();

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
