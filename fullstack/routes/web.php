<?php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KitchenDashboardController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('HomePage');
})->name('home');

// Dashboard Routes (role-based)
Route::middleware(['auth', 'verified'])->group(function () {
    // Generic dashboard route decides redirect based on role
    Route::get('dashboard', function () {
        $user = auth()->user();
        if ($user->isManager()) {
            return redirect()->route('manager.dashboard');
        }
        if ($user->isKitchen()) {
            return redirect()->route('kitchen.dashboard');
        }
        return app(DashboardController::class)->index(); // diner
    })->name('dashboard');

    Route::get('dashboard/kitchen', KitchenDashboardController::class)->middleware('role:kitchen,manager')->name('kitchen.dashboard');
    Route::get('dashboard/manager', ManagerDashboardController::class)->middleware('role:manager')->name('manager.dashboard');
});

// Kitchen API endpoints
Route::middleware(['auth','verified','role:kitchen,manager'])->group(function () {
    Route::get('/api/kitchen/orders', [\App\Http\Controllers\OrderController::class, 'kitchenOrders'])->name('kitchen.orders');
    Route::patch('/api/kitchen/orders/{order}/status', [\App\Http\Controllers\OrderController::class, 'updateStatus'])->name('kitchen.orders.status');
});


//Menu and Order Routes (diner only)
Route::middleware(['auth', 'verified','role:diner'])->group(function () {
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

    // Loyalty Points and Order Management Routes
    Route::get('/loyalty-points', function () {
        return Inertia::render('LoyaltyPoints');
    })->name('loyalty.points.page');
    Route::post('/orders/{orderId}/complete', [OrderController::class, 'completeOrder'])->name('orders.complete');
    Route::get('/api/loyalty-points', [OrderController::class, 'getLoyaltyPoints'])->name('loyalty.points');
    Route::get('/api/order-history', [OrderController::class, 'getOrderHistory'])->name('orders.history');
});


//Reservation Routes (diner only)
Route::middleware(['auth', 'verified','role:diner'])->group(function () {
    Route::get('/reservation', [ReservationController::class, 'index'])->name('reservation.index');
    Route::get('/reservation/availability', [ReservationController::class, 'getTableAvailability'])->name('reservation.availability');
    Route::post('/reservation', [ReservationController::class, 'store'])->name('reservation.store');
    Route::patch('/reservation/{id}/cancel', [ReservationController::class, 'cancel'])->name('reservation.cancel');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
