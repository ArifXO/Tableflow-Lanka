<?php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('HomePage');
})->name('home');

// Dashboard Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});


//Menu and Order Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
});


//Reservation Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reservation', [ReservationController::class, 'index'])->name('reservation.index');
    Route::get('/reservation/availability', [ReservationController::class, 'getTableAvailability'])->name('reservation.availability');
    Route::post('/reservation', [ReservationController::class, 'store'])->name('reservation.store');
    Route::patch('/reservation/{id}/cancel', [ReservationController::class, 'cancel'])->name('reservation.cancel');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
