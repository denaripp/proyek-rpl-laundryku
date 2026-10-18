<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\XenditWebhookController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\CourierTaskController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\StoreOwnerController;
use App\Http\Controllers\Api\StoreDocumentController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Auth\CustomerApiAuthController;
use App\Http\Controllers\Api\Auth\MitraApiAuthController;
use App\Http\Controllers\Api\Auth\CourierApiAuthController;
use App\Http\Controllers\Api\Auth\AdminApiAuthController;
use App\Http\Controllers\Api\Auth\OtpRegistrationApiController;
use App\Http\Controllers\Api\Portal\CustomerPortalController;
use App\Http\Controllers\Api\Portal\MitraPortalController;
use App\Http\Controllers\Api\Portal\CourierPortalController;
use App\Http\Controllers\Api\Portal\AdminPortalController;

// ===== Public webhooks =====
Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle'])
    ->middleware('throttle:webhook')
    ->name('xendit.webhook');

// ===== Public marketplace browsing (auth required, but light permission) =====
Route::middleware('auth')->group(function () {
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/{store}', [StoreController::class, 'show']);
    Route::get('/stores/{store}/services', [StoreController::class, 'services']);
});

Route::middleware(['auth'])->group(function () {
    // ----- Payments -----
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments', [PaymentController::class, 'store']);

    // ----- Refunds -----
    Route::get('/refunds', [RefundController::class, 'index']);
    Route::get('/refunds/{refund}', [RefundController::class, 'show']);
    Route::post('/refunds', [RefundController::class, 'store'])->middleware('throttle:refund');
    Route::post('/refunds/{refund}/process', [RefundController::class, 'process']);
    Route::post('/refunds/{refund}/cancel', [RefundController::class, 'cancel']);

    // ----- Courier task lifecycle (separate from courier dashboard) -----
    Route::patch('/courier-tasks/{task}/accept', [CourierTaskController::class, 'accept']);
    Route::post('/courier-tasks/{task}/reject', [CourierTaskController::class, 'reject']);
    Route::patch('/courier-tasks/{task}/status', [CourierTaskController::class, 'updateStatus']);
    Route::post('/courier-tasks/{task}/location', [CourierTaskController::class, 'updateLocation']);

    // ----- Reviews -----
    Route::get('/stores/{store}/reviews', [ReviewController::class, 'index']);
    Route::post('/orders/{order}/reviews', [ReviewController::class, 'store']);

    // ----- Disputes -----
    Route::get('/disputes', [DisputeController::class, 'index']);
    Route::get('/disputes/{dispute}', [DisputeController::class, 'show']);
    Route::post('/orders/{order}/disputes', [DisputeController::class, 'store'])->middleware('throttle:dispute');
    Route::post('/disputes/{dispute}/review', [DisputeController::class, 'review']);
    Route::post('/disputes/{dispute}/info-request', [DisputeController::class, 'requestInfo']);
    Route::post('/disputes/{dispute}/investigate', [DisputeController::class, 'investigate']);
    Route::post('/disputes/{dispute}/resolve', [DisputeController::class, 'resolve']);
    Route::post('/disputes/{dispute}/reject', [DisputeController::class, 'reject']);

    // ----- Settlements -----
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::get('/settlements/{settlement}', [SettlementController::class, 'show']);
    Route::post('/stores/{store}/settlements', [SettlementController::class, 'store']);
    Route::post('/settlements/{settlement}/verify', [SettlementController::class, 'verify']);
});

// ===== AI Assistant (Browser-facing widget, Session & CSRF protected via 'web' middleware) =====
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/ai/chat', [AiChatController::class, 'chat']);
    Route::get('/ai/conversations', [AiChatController::class, 'index']);
    Route::get('/ai/conversations/{conversation}', [AiChatController::class, 'show']);
});

// ===== Tahap 16: Order core transaction loop (auth required) =====
Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/tracking', [OrderController::class, 'tracking']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/set-weight', [OrderController::class, 'setWeight']);
    Route::post('/orders/{order}/assign-courier', [OrderController::class, 'assignCourier']);
});

// ===== Tahap 16: Customer profile & address =====
Route::middleware('auth')->group(function () {
    Route::get('/customer/profile', [CustomerController::class, 'profile']);
    Route::patch('/customer/profile', [CustomerController::class, 'updateProfile']);
    Route::get('/customer/addresses', [CustomerController::class, 'listAddresses']);
    Route::post('/customer/addresses', [CustomerController::class, 'storeAddress']);
    Route::delete('/customer/addresses/{address}', [CustomerController::class, 'destroyAddress']);
});

// ===== Tahap 16: Courier dashboard =====
Route::middleware('auth')->group(function () {
    Route::get('/courier/dashboard', [CourierController::class, 'dashboard']);
    Route::get('/courier/tasks', [CourierController::class, 'tasks']);
    Route::get('/courier/earnings', [CourierController::class, 'earnings']);
    Route::patch('/courier/availability', [CourierController::class, 'updateAvailability']);
});

// ===== Tahap 16: Store owner / staff endpoints =====
Route::middleware('auth')->group(function () {
    Route::get('/store-owner/stores', [StoreOwnerController::class, 'index']);
    Route::get('/store-owner/stores/{store}', [StoreOwnerController::class, 'show']);
    Route::patch('/store-owner/stores/{store}', [StoreOwnerController::class, 'update']);
    Route::post('/store-owner/stores/{store}/submit', [StoreOwnerController::class, 'submit']);
    Route::get('/store-owner/stores/{store}/orders', [StoreOwnerController::class, 'orders']);
    // SECURITY (Tahap 19): secure file upload
    Route::post('/store-owner/stores/{store}/documents', [StoreDocumentController::class, 'upload']);
    Route::get('/store-owner/stores/{store}/documents', [StoreDocumentController::class, 'index']);
    Route::get('/store-owner/stores/{store}/documents/{doc}/download', [StoreDocumentController::class, 'download']);
    Route::delete('/store-owner/stores/{store}/documents/{doc}', [StoreDocumentController::class, 'destroy']);
});

// ===== Tahap 18: Store dashboard =====
Route::middleware('auth')->group(function () {
    Route::get('/store-owner/dashboard', [\App\Http\Controllers\Api\StoreDashboardController::class, 'dashboard']);
    Route::get('/store-owner/dashboard/services', [\App\Http\Controllers\Api\StoreDashboardController::class, 'services']);
    Route::get('/store-owner/dashboard/staff', [\App\Http\Controllers\Api\StoreDashboardController::class, 'staff']);
    Route::get('/store-owner/dashboard/couriers', [\App\Http\Controllers\Api\StoreDashboardController::class, 'couriers']);
    Route::get('/store-owner/dashboard/reports', [\App\Http\Controllers\Api\StoreDashboardController::class, 'reports']);
});

// ===== Tahap 16: Platform admin dashboard =====
Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/admin/menu', [AdminController::class, 'menu']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/stores', [AdminController::class, 'stores']);
    Route::get('/admin/payments', [AdminController::class, 'payments']);
    Route::get('/admin/refunds', [AdminController::class, 'refunds']);
    Route::get('/admin/disputes', [AdminController::class, 'disputes']);
    Route::get('/admin/notifications', [AdminController::class, 'notifications']);
    Route::get('/admin/audit', [AdminController::class, 'audit']);
    // Tahap 18: owner-only
    Route::get('/admin/commission', [AdminController::class, 'commission']);
    Route::get('/admin/security', [AdminController::class, 'security']);
    Route::get('/admin/ai-usage', [AdminController::class, 'aiUsage']);
    // Tahap 21: system settings + monitoring
    Route::get('/admin/settings', [AdminController::class, 'settingsIndex']);
    Route::patch('/admin/settings/{key}', [AdminController::class, 'settingsUpdate']);
    Route::get('/admin/monitoring', [AdminController::class, 'monitoring']);
});

// =========================================================================
// API V1 — Official Authentication & Protected Portals (Sanctum + RBAC)
// =========================================================================
Route::prefix('v1')->group(function () {

    // ===== Auth Endpoints =====
    Route::prefix('auth')->group(function () {
        // Standard Customer OTP & Client Auth
        Route::post('/register', [OtpRegistrationApiController::class, 'register'])
            ->middleware('throttle:login');
        Route::post('/login', [OtpRegistrationApiController::class, 'login'])
            ->middleware('throttle:login');
        Route::post('/otp/verify', [OtpRegistrationApiController::class, 'verifyOtp'])
            ->middleware('throttle:login');
        Route::post('/otp/send', [OtpRegistrationApiController::class, 'sendOtp'])
            ->middleware('throttle:login');
        Route::post('/logout', [OtpRegistrationApiController::class, 'logout'])
            ->middleware('auth:sanctum');

        // Customer Auth
        Route::post('/customer/register', [CustomerApiAuthController::class, 'register'])
            ->middleware('throttle:login');
        Route::post('/customer/login', [CustomerApiAuthController::class, 'login'])
            ->middleware('throttle:login');
        Route::post('/customer/logout', [CustomerApiAuthController::class, 'logout'])
            ->middleware(['auth:sanctum', 'ability:role:customer', 'role:customer']);

        // Mitra Auth
        Route::post('/mitra/register', [MitraApiAuthController::class, 'register'])
            ->middleware('throttle:login');
        Route::post('/mitra/login', [MitraApiAuthController::class, 'login'])
            ->middleware('throttle:login');
        Route::post('/mitra/2fa', [MitraApiAuthController::class, 'verify2fa'])
            ->middleware(['auth:sanctum', 'ability:2fa-pending', 'throttle:login']);
        Route::post('/mitra/logout', [MitraApiAuthController::class, 'logout'])
            ->middleware(['auth:sanctum', 'ability:role:store_admin', 'role:store_admin']);

        // Courier Auth
        Route::post('/kurir/login', [CourierApiAuthController::class, 'login'])
            ->middleware('throttle:login');
        Route::post('/kurir/logout', [CourierApiAuthController::class, 'logout'])
            ->middleware(['auth:sanctum', 'ability:role:courier', 'role:courier']);

        // Admin Auth
        Route::post('/admin/login', [AdminApiAuthController::class, 'login'])
            ->middleware('throttle:login');
        Route::post('/admin/2fa', [AdminApiAuthController::class, 'verify2fa'])
            ->middleware(['auth:sanctum', 'ability:2fa-pending', 'throttle:login']);
        Route::post('/admin/logout', [AdminApiAuthController::class, 'logout'])
            ->middleware(['auth:sanctum', 'ability:role:super_admin', 'role:super_admin']);
    });

    // ===== Protected Customer Portal =====
    Route::prefix('customer')->middleware(['auth:sanctum', 'ability:role:customer', 'role:customer'])->group(function () {
        Route::get('/profile', [CustomerPortalController::class, 'profile']);
        Route::get('/orders', [CustomerPortalController::class, 'orders']);
    });

    // ===== Protected Mitra Portal =====
    Route::prefix('mitra')->middleware(['auth:sanctum', 'ability:role:store_admin', 'role:store_admin'])->group(function () {
        Route::get('/store', [MitraPortalController::class, 'storeProfile']);
        Route::get('/profile', [MitraPortalController::class, 'storeProfile']);
        Route::get('/orders', [MitraPortalController::class, 'orders']);
    });

    // ===== Protected Courier Portal =====
    Route::prefix('kurir')->middleware(['auth:sanctum', 'ability:role:courier', 'role:courier'])->group(function () {
        Route::get('/tasks', [CourierPortalController::class, 'tasks']);
    });

    // ===== Protected Admin Portal =====
    Route::prefix('admin')->middleware(['auth:sanctum', 'ability:role:super_admin', 'role:super_admin'])->group(function () {
        Route::get('/overview', [AdminPortalController::class, 'overview']);
    });

    // ===== User Session Info =====
    Route::get('/me', [OtpRegistrationApiController::class, 'me'])
        ->middleware('auth:sanctum');
});

