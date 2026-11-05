<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

// Public, view-only pages (anyone, logged in or not)
Route::view('/', 'home')->name('home');
Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability');

// Login and register (logged-out visitors only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

// Any logged-in account (Guest or Admin)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Reservations (guests only; the controller and form request refuse the Admin)
    Route::get('/reserve', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations/{reservation}/proof', [PaymentProofController::class, 'store'])->name('reservations.proof');
});

// Admin only
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('admin.reservations.show');
    Route::post('/reservations/{reservation}/confirm', [AdminReservationController::class, 'confirm'])->name('admin.reservations.confirm');
    Route::post('/reservations/{reservation}/flag', [AdminReservationController::class, 'flag'])->name('admin.reservations.flag');
    Route::get('/proofs/{proof}', [AdminPaymentProofController::class, 'show'])->name('admin.proofs.show');
});
