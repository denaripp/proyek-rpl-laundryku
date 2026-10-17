<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PageController;
use Illuminate\Support\Facades\Route;

// ===== Public marketing pages =====
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/marketplace', [PageController::class, 'marketplace'])->name('marketplace.index');
Route::get('/stores/{slug}', [PageController::class, 'storeDetail'])->name('store.detail');
Route::get('/onboard', [PageController::class, 'storeOnboardForm'])->name('store.onboard');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    // SECURITY (Tahap 19): named login rate limiter (5/min per email+IP)
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    // SECURITY (Tahap 22): manual register is admin/owner-only — public
    // customers must use Google OAuth (PRD §7.6). Gate 'manage_internal_users'.
    // Public visitors are redirected to Google at controller level.

    // Google OAuth (primary customer registration / login path)
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

// Authenticated routes (no 2FA yet)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // SECURITY (Tahap 22): manual register is for INTERNAL users (admin
    // provisioning Store Owner / Courier / Platform Admin). Requires
    // manage_internal_users permission (enforced at controller level).
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // ===== Customer journey (Tahap 17) =====
    Route::post('/onboard', [PageController::class, 'storeOnboardSubmit'])->name('store.onboard.submit');
    Route::get('/stores/{store}/checkout', [PageController::class, 'checkout'])->name('checkout');
    Route::get('/orders/{order}/tracking', [PageController::class, 'tracking'])->name('order.tracking');
    Route::get('/orders/{order}/payment', [PageController::class, 'payment'])->name('order.payment');
    Route::get('/customer/dashboard', [PageController::class, 'dashboard'])->name('customer.dashboard');

    // 2FA
    Route::get('/2fa/verify', [TwoFactorController::class, 'showVerifyForm'])->name('2fa.verify');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify.post');
    Route::get('/2fa/enable', [TwoFactorController::class, 'showEnableForm'])->name('2fa.enable');
    Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable.post');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');
});

// Protected routes (require 2FA if enabled) — internal dashboards + Tahap 16 2FA test
Route::middleware(['auth', '2fa'])->group(function () {
    // ===== Internal dashboards (Tahap 18) — protected by 2FA =====
    // F-1.1 fix: /dash/* dipindah ke group auth+2fa untuk konsistensi dengan /dashboard.
    // Sebelumnya: hanya auth. Sekarang: auth+2fa (jika user 2FA enabled, wajib verify dulu).
    Route::get('/dash', [DashboardController::class, 'root'])->name('dashboard.root');
    Route::get('/dash/store', [DashboardController::class, 'storeDashboard'])->name('store.dashboard');
    Route::get('/dash/courier', [DashboardController::class, 'courierDashboard'])->name('courier.dashboard');
    Route::post('/dash/courier/availability', [DashboardController::class, 'courierToggleAvailability'])->name('courier.availability');
    // Platform Admin & Owner routes (Strictly protected by EnsurePlatformAdmin middleware)
    Route::middleware('admin')->group(function () {
        Route::get('/dash/admin', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');
        Route::post('/dash/admin/stores/{store}/approve', [DashboardController::class, 'adminApproveStore'])->name('admin.stores.approve');
        Route::post('/dash/admin/stores/{store}/reject', [DashboardController::class, 'adminRejectStore'])->name('admin.stores.reject');
        Route::post('/dash/admin/stores/{store}/suspend', [DashboardController::class, 'adminSuspendStore'])->name('admin.stores.suspend');
        Route::post('/dash/admin/stores/{store}/reactivate', [DashboardController::class, 'adminReactivateStore'])->name('admin.stores.reactivate');
    });

    // /dashboard alias (Tahap 16 backward compat)
    Route::get('/dashboard', [DashboardController::class, 'root'])->name('dashboard');
    Route::get('/complete-profile', [AuthController::class, 'showCompleteProfile'])->name('complete-profile');
    Route::post('/complete-profile', [AuthController::class, 'updateCompleteProfile'])->name('complete-profile.update');
});