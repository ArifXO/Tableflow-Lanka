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
        $orders = Order::with(['orderItems.dish'])
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

        return Inertia::render('Dashboard', [
            'orders' => $orders,
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
}
