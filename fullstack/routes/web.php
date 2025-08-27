<?php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KitchenDashboardController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReservationController;
// Removed ManagerOrderController (to be reimplemented)
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
    // Manager orders & payments page
    Route::get('dashboard/manager/orders', function(){ return Inertia::render('ManagerOrders'); })->middleware('role:manager')->name('manager.orders.page');
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
    // Live dashboard orders polling endpoint
    Route::get('/api/dashboard/orders', [DashboardController::class,'ordersApi'])->name('dashboard.orders.api');

    // Loyalty Points and Order Management Routes
    Route::get('/loyalty-points', function () {
        return Inertia::render('LoyaltyPoints');
    })->name('loyalty.points.page');
    Route::get('/orders/history', function () {
        return Inertia::render('Orders/History');
    })->name('orders.history.page');
    // Minimal Bill split & payment routes (refactored)
    Route::get('/api/orders/{order}/bill-split', [\App\Http\Controllers\BillSplitController::class,'apiShow'])->name('orders.bill_split.api');
    Route::post('/orders/{order}/bill-split', [\App\Http\Controllers\BillSplitController::class,'store'])->name('orders.bill_split.store');
    Route::post('/orders/{order}/payments', [\App\Http\Controllers\PaymentController::class,'store'])->name('orders.payments.store');
    Route::post('/orders/{orderId}/complete', [OrderController::class, 'completeOrder'])->name('orders.complete');
    Route::get('/api/loyalty-points', [OrderController::class, 'getLoyaltyPoints'])->name('loyalty.points');
    Route::get('/api/order-history', [OrderController::class, 'getOrderHistory'])->name('orders.history');
    // CSRF helper endpoint (returns a fresh token for AJAX retries)
    Route::get('/api/csrf-token', function(){ return response()->json(['token'=>csrf_token()]); })->name('csrf.token');
});


//Reservation Routes (diner only)
Route::middleware(['auth', 'verified','role:diner'])->group(function () {
    Route::get('/reservation', [ReservationController::class, 'index'])->name('reservation.index');
    Route::get('/reservation/availability', [ReservationController::class, 'getTableAvailability'])->name('reservation.availability');
    Route::post('/reservation', [ReservationController::class, 'store'])->name('reservation.store');
    Route::patch('/reservation/{id}/cancel', [ReservationController::class, 'cancel'])->name('reservation.cancel');

    // Bill Split & Tip
    // (legacy area) already covered above
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Manager API endpoints (placed after auth includes to ensure middleware definitions exist)
Route::middleware(['auth','verified','role:manager'])->group(function(){
    Route::get('/api/manager/orders', [\App\Http\Controllers\ManagerOrderController::class,'index'])->name('manager.api.orders');
    Route::post('/api/manager/payments/{payment}/confirm', [\App\Http\Controllers\ManagerOrderController::class,'confirmPayment'])->name('manager.api.payments.confirm');
});
