<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WalkInController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

// Public, view-only pages (anyone, logged in or not)
Route::view('/', 'home')->name('home');
Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability');
Route::view('/faq', 'faq')->name('faq');
Route::get('/chat/faq', [FaqController::class, 'data'])->name('chat.faq');
Route::post('/chat', [ChatController::class, 'store'])->middleware('throttle:chat')->name('chat.ask');

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

    // Notifications (guest and Admin each see their own)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    // Reservations (guests only; the controller and form request refuse the Admin)
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reserve', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/reservations/{reservation}/request-cancel', [ReservationController::class, 'requestCancel'])->name('reservations.request-cancel');
    Route::post('/reservations/{reservation}/request-reschedule', [ReservationController::class, 'requestReschedule'])->name('reservations.request-reschedule');
    Route::post('/reservations/{reservation}/proof', [PaymentProofController::class, 'store'])->name('reservations.proof');
});

// Admin only
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('admin.reservations.show');
    Route::post('/reservations/{reservation}/confirm', [AdminReservationController::class, 'confirm'])->name('admin.reservations.confirm');
    Route::post('/reservations/{reservation}/flag', [AdminReservationController::class, 'flag'])->name('admin.reservations.flag');
    Route::post('/reservations/{reservation}/approve', [AdminReservationController::class, 'approve'])->name('admin.reservations.approve');
    Route::post('/reservations/{reservation}/decline', [AdminReservationController::class, 'decline'])->name('admin.reservations.decline');
    Route::post('/reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel'])->name('admin.reservations.cancel');
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('admin.activity-log');
    Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('admin.users.deactivate');
    Route::post('/users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('admin.users.reactivate');
    Route::get('/walk-ins/create', [WalkInController::class, 'create'])->name('walk-ins.create');
    Route::post('/walk-ins', [WalkInController::class, 'store'])->name('walk-ins.store');
    Route::get('/proofs/{proof}', [AdminPaymentProofController::class, 'show'])->name('admin.proofs.show');
});
