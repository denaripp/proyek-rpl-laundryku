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
use App\Http\Controllers\Api\Portal\NotificationPortalController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ChatController;

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
Route::middleware(['web', 'auth:web,admin,mitra,kurir'])->group(function () {
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
    Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);
    Route::get('/orders/{order}/payment', [PaymentController::class, 'showOrderPayment']);
    Route::post('/orders/{order}/payment/simulate-success', [PaymentController::class, 'simulateSuccess']);
});

// ===== Tahap 16: Customer profile & address =====
Route::middleware('auth')->group(function () {
    Route::get('/customer/profile', [CustomerController::class, 'profile']);
    Route::patch('/customer/profile', [CustomerController::class, 'updateProfile']);
    Route::get('/customer/addresses', [CustomerController::class, 'listAddresses']);
    Route::post('/customer/addresses', [CustomerController::class, 'storeAddress']);
    Route::delete('/customer/addresses/{address}', [CustomerController::class, 'destroyAddress']);
    Route::post('/customer/geocode/reverse', [CustomerController::class, 'reverseGeocode'])->middleware('throttle:30,1');
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
    Route::get('/store/payout-account', [MitraPortalController::class, 'getPayoutAccount']);
    Route::put('/store/payout-account', [MitraPortalController::class, 'updatePayoutAccount']);
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
            ->middleware(['auth:sanctum', 'throttle:login']);
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

    // ===== Public Marketplace & Stores (Guest & Customer) =====
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/nearby', [StoreController::class, 'index']);
    Route::get('/stores/lookup', [StoreController::class, 'lookup']);
    Route::get('/stores/{store}', [StoreController::class, 'show']);
    Route::get('/stores/{store}/services', [StoreController::class, 'services']);
    Route::get('/stores/{store}/ratings', [StoreController::class, 'ratings']);
    Route::get('/stores/{store}/reviews', [ReviewController::class, 'index']);
    Route::get('/service-categories', [StoreController::class, 'categories']);
    Route::post('/pricing/estimate', [StoreController::class, 'estimatePrice']);
    Route::match(['get', 'post'], '/geocode/forward', [CustomerController::class, 'forwardGeocode'])->middleware('throttle:60,1');
    Route::match(['get', 'post'], '/geocode/reverse', [CustomerController::class, 'reverseGeocode'])->middleware('throttle:60,1');

    // ===== Public Chatbot FAQ & Guest Prompt =====
    Route::prefix('chatbot')->group(function () {
        Route::get('/faq', [ChatbotController::class, 'faq']);
        Route::post('/guest/ask', [ChatbotController::class, 'guestAsk'])->middleware('throttle:guest_ai');
    });

    // ===== Protected Customer & User Endpoints =====
    Route::middleware(['auth:sanctum', '2fa'])->group(function () {
        Route::get('/profile', [CustomerController::class, 'profile']);
        Route::put('/profile', [CustomerController::class, 'updateProfile']);
        Route::patch('/profile', [CustomerController::class, 'updateProfile']);
        Route::post('/profile/photo', [CustomerController::class, 'uploadPhoto']);
        Route::delete('/profile/photo', [CustomerController::class, 'deletePhoto']);

        Route::get('/addresses', [CustomerController::class, 'listAddresses']);
        Route::post('/addresses', [CustomerController::class, 'storeAddress']);
        Route::put('/addresses/{address}', [CustomerController::class, 'updateAddress']);
        Route::delete('/addresses/{address}', [CustomerController::class, 'destroyAddress']);
        Route::post('/addresses/{address}/set-default', [CustomerController::class, 'setDefaultAddress']);
        Route::match(['get', 'post'], '/geocode/forward', [CustomerController::class, 'forwardGeocode'])->middleware('throttle:60,1');
        Route::post('/geocode/reverse', [CustomerController::class, 'reverseGeocode'])->middleware('throttle:30,1');

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::get('/orders/{order}/tracking', [OrderController::class, 'tracking']);
        Route::post('/orders/{order}/transition', [OrderController::class, 'transition']);
        Route::post('/orders/{order}/assign-courier', [OrderController::class, 'assignCourier']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('/orders/{order}/rating', [ReviewController::class, 'orderRating']);
        Route::post('/orders/{order}/rating', [ReviewController::class, 'storeRating']);
        Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);
        Route::get('/orders/{order}/payment', [PaymentController::class, 'showOrderPayment']);
        Route::post('/orders/{order}/payment/simulate-success', [PaymentController::class, 'simulateSuccess']);

        Route::get('/upgrades/courier', [CustomerController::class, 'courierUpgradeStatus']);
        Route::post('/upgrades/courier', [CustomerController::class, 'applyCourierUpgrade']);
        Route::get('/upgrades/store', [CustomerController::class, 'storeUpgradeStatus']);
        Route::post('/upgrades/store', [CustomerController::class, 'applyStoreUpgrade']);

        // ===== Chatbot AI Assistant Sessions & Messages =====
        Route::prefix('chatbot')->group(function () {
            Route::get('/sessions', [ChatbotController::class, 'index']);
            Route::post('/sessions', [ChatbotController::class, 'store']);
            Route::delete('/sessions', [ChatbotController::class, 'destroyAll']);
            Route::get('/sessions/{id}', [ChatbotController::class, 'show']);
            Route::post('/sessions/{id}/messages', [ChatbotController::class, 'sendMessage']);
            Route::delete('/sessions/{id}', [ChatbotController::class, 'destroy']);
        });

        // ===== Human Chat Customer <-> Store (FR-CHAT, US-CHAT) =====
        Route::prefix('conversations')->group(function () {
            Route::get('/unread-count', [ChatController::class, 'unreadCount']);
            Route::get('/', [ChatController::class, 'index']);
            Route::post('/', [ChatController::class, 'startOrGet']);
            Route::get('/{id}', [ChatController::class, 'show']);
            Route::post('/{id}/read', [ChatController::class, 'markAsRead']);
            Route::post('/{id}/messages', [ChatController::class, 'sendMessage']);
            Route::get('/{id}/messages', [ChatController::class, 'getMessages']);
            Route::get('/{id}/attachments/{messageId}', [ChatController::class, 'streamAttachment']);
        });
        Route::get('/chat/unread-count', [ChatController::class, 'unreadCount']);
    });

    // ===== Protected Customer Portal =====
    Route::prefix('customer')->middleware(['auth:sanctum', 'ability:role:customer', 'role:customer'])->group(function () {
        Route::get('/profile', [CustomerPortalController::class, 'profile']);
        Route::get('/orders', [CustomerPortalController::class, 'orders']);
    });

    // ===== Protected Mitra Portal =====
    Route::prefix('mitra')->middleware(['auth:sanctum', 'ability:role:store_admin', 'role:store_admin'])->group(function () {
        Route::get('/store', [MitraPortalController::class, 'storeProfile']);
        Route::patch('/store', [MitraPortalController::class, 'updateStoreProfile']);
        Route::put('/password', [MitraPortalController::class, 'updatePassword'])->middleware('throttle:10,1');
        Route::get('/profile', [MitraPortalController::class, 'storeProfile']);
        Route::get('/orders', [MitraPortalController::class, 'orders']);
        Route::get('/payment-methods', [MitraPortalController::class, 'getPaymentMethods']);
        Route::put('/payment-methods', [MitraPortalController::class, 'updatePaymentMethods']);
        Route::get('/payout-account', [MitraPortalController::class, 'getPayoutAccount']);
        Route::put('/payout-account', [MitraPortalController::class, 'updatePayoutAccount']);
    });

    Route::middleware(['auth:sanctum', 'ability:role:store_admin', 'role:store_admin'])->group(function () {
        Route::prefix('store')->group(function () {
            Route::get('/dashboard', [MitraPortalController::class, 'dashboard']);
            Route::get('/sidebar-counts', [MitraPortalController::class, 'sidebarCounts']);
            Route::patch('/operational-status', [MitraPortalController::class, 'updateOperationalStatus']);
            Route::get('/services', [MitraPortalController::class, 'services']);
            Route::post('/services', [MitraPortalController::class, 'createService']);
            Route::patch('/services/{id}', [MitraPortalController::class, 'updateService']);
            Route::delete('/services/{id}', [MitraPortalController::class, 'deleteService']);
            Route::patch('/services/{id}/status', [MitraPortalController::class, 'toggleServiceStatus']);
            Route::post('/services/bulk-status', [MitraPortalController::class, 'bulkServiceStatus']);
            Route::get('/ledger', [MitraPortalController::class, 'ledger']);
            Route::get('/payment-methods', [MitraPortalController::class, 'getPaymentMethods']);
            Route::put('/payment-methods', [MitraPortalController::class, 'updatePaymentMethods']);
            Route::get('/payout-account', [MitraPortalController::class, 'getPayoutAccount']);
            Route::put('/payout-account', [MitraPortalController::class, 'updatePayoutAccount']);
            Route::get('/reports', [MitraPortalController::class, 'reports']);
            Route::get('/pricing-rules', [MitraPortalController::class, 'getPricingRules']);
            Route::put('/pricing-rules', [MitraPortalController::class, 'updatePricingRules']);
            Route::post('/orders/{order}/confirm-cod', [MitraPortalController::class, 'confirmCod']);
        });

        Route::prefix('store-admin')->group(function () {
            Route::get('/couriers', [MitraPortalController::class, 'couriers']);
            Route::post('/couriers/recruit/{courierProfileId}', [MitraPortalController::class, 'recruitCourier']);
            Route::post('/couriers/terminate/{assignmentId}', [MitraPortalController::class, 'terminateCourier']);
            Route::post('/couriers/reject/{courierProfileId}', [MitraPortalController::class, 'rejectCourier']);
            Route::get('/ratings', [MitraPortalController::class, 'ratings']);
            Route::get('/complaints', [MitraPortalController::class, 'complaints']);
            Route::patch('/complaints/{id}/status', [MitraPortalController::class, 'updateComplaintStatus']);
        });
    });

    // ===== Protected Courier Portal =====
    Route::prefix('kurir')->middleware(['auth:sanctum', 'ability:role:courier', 'role:courier'])->group(function () {
        Route::get('/tasks', [CourierPortalController::class, 'tasks']);
    });

    Route::prefix('courier')->middleware(['auth:sanctum', 'ability:role:courier', 'role:courier'])->group(function () {
        Route::get('/profile', [CourierPortalController::class, 'profile']);
        Route::get('/tasks', [CourierPortalController::class, 'taskList']);
        Route::get('/tasks/{orderId}', [CourierPortalController::class, 'taskDetail']);
        Route::post('/tasks/{orderId}/claim', [CourierPortalController::class, 'claimTask']);
        Route::post('/tasks/{orderId}/transition', [CourierPortalController::class, 'transitionTask']);
        Route::post('/tasks/{orderId}/confirm-cod', [CourierPortalController::class, 'confirmCod']);
    });

    // ===== User Notifications Portal (Customer, Mitra, Courier) =====
    Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [NotificationPortalController::class, 'index']);
        Route::patch('/read-all', [NotificationPortalController::class, 'markAllAsRead']);
        Route::patch('/{id}/read', [NotificationPortalController::class, 'markAsRead']);
        Route::get('/preferences', [NotificationPortalController::class, 'preferences']);
        Route::put('/preferences', [NotificationPortalController::class, 'updatePreferences']);
    });

    // ===== Protected Admin Portal =====
    Route::prefix('admin')->middleware(['auth:sanctum', 'ability:role:super_admin', 'role:super_admin'])->group(function () {
        Route::get('/overview', [AdminPortalController::class, 'overview']);
        Route::get('/dashboard/metrics', [AdminPortalController::class, 'metrics']);

        // 1. Orders
        Route::get('/orders', [AdminPortalController::class, 'orders']);
        Route::get('/orders/{id}', [AdminPortalController::class, 'orderDetail']);
        Route::post('/orders/{id}/assign-courier', [AdminPortalController::class, 'assignCourier']);

        // 2. Payments
        Route::get('/payments', [AdminPortalController::class, 'payments']);
        Route::get('/payments/{id}', [AdminPortalController::class, 'paymentDetail']);

        // 3. Stores & Approvals
        Route::get('/stores', [AdminPortalController::class, 'stores']);
        Route::get('/stores/{id}', [AdminPortalController::class, 'storeDetail']);
        Route::post('/stores/{id}/approve', [AdminPortalController::class, 'approveStore']);
        Route::post('/stores/{id}/reject', [AdminPortalController::class, 'rejectStore']);

        // 4. Users
        Route::get('/users', [AdminPortalController::class, 'users']);
        Route::get('/users/{id}', [AdminPortalController::class, 'userDetail']);
        Route::patch('/users/{id}/status', [AdminPortalController::class, 'updateUserStatus']);

        // 5. Categories
        Route::get('/categories', [AdminPortalController::class, 'categories']);
        Route::post('/categories', [AdminPortalController::class, 'createCategory']);
        Route::put('/categories/{id}', [AdminPortalController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [AdminPortalController::class, 'deleteCategory']);

        // 6. Platform Commission
        Route::get('/platform-settings/commission', [AdminPortalController::class, 'getCommission']);
        Route::patch('/platform-settings/commission', [AdminPortalController::class, 'updateCommission']);

        // 7. Audit Logs
        Route::get('/audit-logs', [AdminPortalController::class, 'auditLogs']);
        Route::get('/audit-logs/{id}', [AdminPortalController::class, 'auditLogDetail']);

        // 8. AI Assistant Settings & Model Configuration
        Route::get('/ai-settings', [AdminPortalController::class, 'getAiSettings']);
        Route::put('/ai-settings', [AdminPortalController::class, 'updateAiSettings']);
        Route::post('/ai-settings/test', [AdminPortalController::class, 'testAiModel']);
    });

    // ===== User Session Info =====
    Route::get('/me', [OtpRegistrationApiController::class, 'me'])
        ->middleware(['auth:sanctum', '2fa']);
});

