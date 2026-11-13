<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\CourierAuthController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\MitraAuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PageController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// 1. PUBLIC MARKETING & STORE BROWSING
// =========================================================================
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/marketplace', [PageController::class, 'marketplace'])->name('marketplace.index');
Route::get('/stores/{slug}', [PageController::class, 'storeDetail'])->name('store.detail');
Route::get('/onboard', [PageController::class, 'storeOnboardForm'])->name('store.onboard');

// =========================================================================
// 2. DOOR 1: CUSTOMER (Guard: web)
// =========================================================================
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Google OAuth
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware(['auth:web', '2fa'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/customer/dashboard', [PageController::class, 'dashboard'])->name('customer.dashboard');
    Route::post('/onboard', [PageController::class, 'storeOnboardSubmit'])->name('store.onboard.submit');
    Route::get('/stores/{store}/checkout', [PageController::class, 'checkout'])->name('checkout');
    Route::get('/orders/{order}/tracking', [PageController::class, 'tracking'])->name('order.tracking');
    Route::get('/orders/{order}/payment', [PageController::class, 'payment'])->name('order.payment');

    // Customer 2FA (optional)
    Route::get('/2fa/verify', [TwoFactorController::class, 'showVerifyForm'])->name('2fa.verify');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify.post');
    Route::get('/2fa/enable', [TwoFactorController::class, 'showEnableForm'])->name('2fa.enable');
    Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable.post');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');

    Route::get('/complete-profile', [AuthController::class, 'showCompleteProfile'])->name('complete-profile');
    Route::post('/complete-profile', [AuthController::class, 'updateCompleteProfile'])->name('complete-profile.update');
});

// =========================================================================
// 3. DOOR 2: MITRA / STORE ADMIN (Guard: mitra)
// =========================================================================
Route::prefix('mitra')->name('mitra.')->group(function () {
    Route::middleware('guest:mitra')->group(function () {
        Route::get('/login', [MitraAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [MitraAuthController::class, 'login'])->middleware('throttle:login');

        Route::get('/register', [MitraAuthController::class, 'showRegisterForm'])->name('register');
        Route::post('/register', [MitraAuthController::class, 'register']);
    });

    Route::middleware(['auth:mitra', '2fa'])->group(function () {
        Route::post('/logout', [MitraAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'storeDashboard'])->name('dashboard');
        Route::get('/', fn() => redirect()->route('mitra.dashboard'));
    });
});

// =========================================================================
// 4. DOOR 3: COURIER (Guard: kurir)
// =========================================================================
Route::prefix('kurir')->name('kurir.')->group(function () {
    Route::middleware('guest:kurir')->group(function () {
        Route::get('/login', [CourierAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [CourierAuthController::class, 'login'])->middleware('throttle:login');
    });

    Route::middleware(['auth:kurir', '2fa'])->group(function () {
        Route::post('/logout', [CourierAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'courierDashboard'])->name('dashboard');
        Route::post('/availability', [DashboardController::class, 'courierToggleAvailability'])->name('availability');
        Route::get('/', fn() => redirect()->route('kurir.dashboard'));
    });
});

// =========================================================================
// 5. DOOR 4: SUPER ADMIN (Guard: admin)
// =========================================================================
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');
        Route::get('/2fa', [AdminAuthController::class, 'show2faForm'])->name('2fa');
        Route::post('/2fa', [AdminAuthController::class, 'verify2fa'])->name('2fa.verify');
    });

    Route::middleware(['auth:admin', '2fa'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard');
        Route::post('/stores/{store}/approve', [DashboardController::class, 'adminApproveStore'])->name('stores.approve');
        Route::post('/stores/{store}/reject', [DashboardController::class, 'adminRejectStore'])->name('stores.reject');
        Route::post('/stores/{store}/suspend', [DashboardController::class, 'adminSuspendStore'])->name('stores.suspend');
        Route::post('/stores/{store}/reactivate', [DashboardController::class, 'adminReactivateStore'])->name('stores.reactivate');
        Route::get('/', fn() => redirect()->route('admin.dashboard'));
    });
});

// =========================================================================
// 6. BACKWARD COMPATIBILITY / INTERNAL REDIRECT ROUTING
// =========================================================================
Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/dash', [DashboardController::class, 'root'])->name('dashboard.root');
    Route::get('/dash/store', [DashboardController::class, 'storeDashboard'])->name('store.dashboard');
    Route::get('/dash/courier', [DashboardController::class, 'courierDashboard'])->name('courier.dashboard');
    Route::post('/dash/courier/availability', [DashboardController::class, 'courierToggleAvailability'])->name('courier.availability');
    Route::middleware('admin')->group(function () {
        Route::get('/dash/admin', [DashboardController::class, 'adminDashboard'])->name('dash.admin.dashboard');
        Route::post('/dash/admin/stores/{store}/approve', [DashboardController::class, 'adminApproveStore'])->name('dash.admin.stores.approve');
        Route::post('/dash/admin/stores/{store}/reject', [DashboardController::class, 'adminRejectStore'])->name('dash.admin.stores.reject');
        Route::post('/dash/admin/stores/{store}/suspend', [DashboardController::class, 'adminSuspendStore'])->name('dash.admin.stores.suspend');
        Route::post('/dash/admin/stores/{store}/reactivate', [DashboardController::class, 'adminReactivateStore'])->name('dash.admin.stores.reactivate');
    });
    Route::get('/dashboard', [DashboardController::class, 'root'])->name('dashboard');
});