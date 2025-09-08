<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Table;
use Illuminate\Support\Carbon;
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

        // Daily report
        $today = Carbon::today();
        $dailyOrders = Order::whereDate('created_at', $today)->get();
        $dailySales = (float) $dailyOrders->sum('total_amount');
        $dailyReservationCount = Reservation::whereDate('reservation_date', $today)->count();

        // Table turnover
        $activeTables = Table::where('is_active', true)->count();
        $tableTurnoverRate = $activeTables > 0 ? round($dailyReservationCount / $activeTables, 2) : 0.0;

        // Top selling menu items (
        $topItems = OrderItem::selectRaw('dish_id, SUM(quantity) as qty, SUM(quantity * price) as revenue')
            ->whereHas('order', fn($q) => $q->whereDate('created_at', $today))
            ->groupBy('dish_id')
            ->orderByDesc('qty')
            ->with('dish')
            ->limit(5)
            ->get()
            ->map(fn($row) => [
                'dish_id' => $row->dish_id,
                'name' => $row->dish?->name_en ?? $row->dish?->name_bn ?? 'Item',
                'quantity' => (int) $row->qty,
                'revenue' => (float) $row->revenue,
            ]);

        $dailyReport = [
            'date' => $today->toDateString(),
            'sales' => $dailySales,
            'reservation_count' => $dailyReservationCount,
            'table_turnover_rate' => $tableTurnoverRate,
            'top_items' => $topItems,
        ];

        return Inertia::render('ManagerDashboard', [
            'recentOrders' => $recentOrders,
            'recentReservations' => $recentReservations,
            'metrics' => $metrics,
            'user' => $user,
            'dailyReport' => $dailyReport,
        ]);
    }
}
