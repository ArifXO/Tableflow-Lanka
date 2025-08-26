<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ManagerDashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();
        if (!$user || !$user->isManager()) {
            abort(403);
        }

        $recentOrders = Order::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentReservations = Reservation::with(['user','table'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $metrics = [
            'users' => User::count(),
            'orders' => Order::count(),
            'reservations' => Reservation::count(),
            'active_reservations' => Reservation::whereIn('status', ['confirmed'])->count(),
            'revenue' => (float) Order::sum('total_amount'),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
        ];

        return Inertia::render('ManagerDashboard', [
            'recentOrders' => $recentOrders,
            'recentReservations' => $recentReservations,
            'metrics' => $metrics,
            'user' => $user,
        ]);
    }
}
