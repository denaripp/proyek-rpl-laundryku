<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\CourierTaskController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlatformSettingController;
use App\Http\Controllers\Api\PriceEstimationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StoreCourierController;
use App\Http\Controllers\Api\StoreLedgerController;
use App\Http\Controllers\Api\StoreServiceController;
use App\Http\Controllers\Api\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminStoreController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Api\UpgradeController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// System Health Check
Route::get('/health', [HealthController::class, 'index']);

// Debug Endpoints (Local/Testing Only)
if (app()->environment('local', 'testing')) {
    Route::prefix('debug')->middleware('throttle:5,1')->group(function () {
        Route::post('/fonnte', [DebugController::class, 'testFonnte']);
        Route::post('/otp', [DebugController::class, 'testOtp']);
    });
}

// Public Service Categories (FR-MKT-08)
Route::get('/service-categories', [ServiceCategoryController::class, 'index']);

// Public Marketplace & Stores (FR-MKT, US-CUS-03, US-CUS-04)
Route::prefix('stores')->group(function () {
    Route::get('/lookup', [StoreController::class, 'lookup']);
    Route::get('/', [StoreController::class, 'index']);
    Route::get('/nearby', [StoreController::class, 'nearby']);
    Route::get('/{id}', [StoreController::class, 'show']);
    Route::get('/{id}/ratings', [RatingController::class, 'storeRatings']);
});


// Public Payment Webhook Callbacks (FR-PAY-05, FR-PAY-06)
Route::prefix('webhooks')->group(function () {
    Route::post('/xendit', [WebhookController::class, 'handleXendit']);
});

// Public Chatbot FAQ & Guest Prompt (FR-AI-01, FR-AI-02)
Route::prefix('chatbot')->group(function () {
    Route::get('/faq', [ChatbotController::class, 'faq']);
    Route::post('/guest/ask', [ChatbotController::class, 'guestAsk'])->middleware('throttle:ai');
});

// Price Estimation (FR-PRICE, US-CUS-05)
Route::post('/pricing/estimate', [PriceEstimationController::class, 'estimate']);

// Super Admin Dedicated Auth (Public/Throttled)
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');
});

// Authentication Routes (FR-AUTH)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:otp');
    Route::post('/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/password/request-setup', [AuthController::class, 'requestPasswordSetup'])->middleware('throttle:otp');
    Route::post('/password/setup', [AuthController::class, 'setupPassword'])->middleware('throttle:otp');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Authenticated Routes (FR-PROFILE, US-COU-01, US-STORE-01)
Route::middleware('auth:sanctum')->group(function () {
    // Current User Session & Avatar shortcuts
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/mode', [AuthController::class, 'switchMode']);
    Route::post('/me/avatar', [ProfileController::class, 'uploadPhoto']);
    Route::delete('/me/avatar', [ProfileController::class, 'deletePhoto']);

    // Profile CRUD & Photo Upload
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/photo', [ProfileController::class, 'uploadPhoto']);
        Route::delete('/photo', [ProfileController::class, 'deletePhoto']);
        Route::get('/photo', [ProfileController::class, 'streamPhoto']);
    });

    // Customer Addresses CRUD
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::get('/{id}', [AddressController::class, 'show']);
        Route::put('/{id}', [AddressController::class, 'update']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
        Route::post('/{id}/set-default', [AddressController::class, 'setDefault']);
    });

    // Upgrades Application (Courier & Store)
    Route::prefix('upgrades')->group(function () {
        Route::post('/courier', [UpgradeController::class, 'applyCourier']);
        Route::get('/courier', [UpgradeController::class, 'courierStatus']);
        Route::post('/store', [UpgradeController::class, 'applyStore']);
        Route::get('/store', [UpgradeController::class, 'storeStatus']);
    });

    // Orders & State Machine (FR-ORDER, US-CUS-05, US-CUS-07, PRD 7.3)
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::get('/{id}/tracking', [OrderController::class, 'tracking']);
        Route::post('/{id}/transition', [OrderController::class, 'transition']);
        Route::post('/{id}/price-approval', [OrderController::class, 'priceApproval']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);

        // Payment processing (FR-PAY-01..04)
        Route::post('/{id}/pay', [PaymentController::class, 'pay']);
        Route::get('/{id}/payment', [PaymentController::class, 'show']);
        Route::post('/{id}/payment/simulate-success', [PaymentController::class, 'simulateSuccess']);

        // Complaints & Ratings (FR-COM-01, FR-RATE-01)
        Route::post('/{id}/rating', [RatingController::class, 'store']);
        Route::get('/{id}/rating', [RatingController::class, 'showByOrder']);
        Route::post('/{id}/complaints', [ComplaintController::class, 'store']);
        Route::get('/{id}/complaints', [ComplaintController::class, 'index']);
    });

    // Customer Complaints (FR-COM-01, FR-COM-02)
    Route::prefix('complaints')->group(function () {
        Route::get('/', [ComplaintController::class, 'index']);
        Route::get('/{id}', [ComplaintController::class, 'show']);
        Route::get('/{id}/attachments/{attachmentId}', [ComplaintController::class, 'streamAttachment']);
    });

    // Multi-Channel Notifications & Preferences (PRD 11.8, FR-NOTIF)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
    });

    // Store Admin Dashboard & Operations (FR-STORE)
    Route::prefix('store')->group(function () {
        Route::get('/sidebar-counts', [\App\Http\Controllers\Api\StoreDashboardController::class, 'sidebarCounts']);
        Route::get('/metrics', [\App\Http\Controllers\Api\StoreDashboardController::class, 'metrics']);
        Route::get('/dashboard', [\App\Http\Controllers\Api\StoreDashboardController::class, 'metrics']);
        Route::patch('/operational-status', [\App\Http\Controllers\Api\StoreDashboardController::class, 'toggleOperationalStatus']);
    });

    // Store Admin Services Management (FR-STORE-05)
    Route::prefix('store/services')->group(function () {
        Route::get('/', [StoreServiceController::class, 'index']);
        Route::post('/', [StoreServiceController::class, 'store']);
        Route::post('/bulk-status', [StoreServiceController::class, 'bulkStatus']);
        Route::get('/{id}', [StoreServiceController::class, 'show']);
        Route::patch('/{id}', [StoreServiceController::class, 'update']);
        Route::put('/{id}', [StoreServiceController::class, 'update']);
        Route::patch('/{id}/status', [StoreServiceController::class, 'updateStatus']);
        Route::patch('/{id}/toggle-status', [StoreServiceController::class, 'toggleStatus']);
        Route::delete('/{id}', [StoreServiceController::class, 'destroy']);
    });

    // Backward compatibility alias for store-admin/services
    Route::prefix('store-admin/services')->group(function () {
        Route::get('/', [StoreServiceController::class, 'index']);
        Route::post('/', [StoreServiceController::class, 'store']);
        Route::post('/bulk-status', [StoreServiceController::class, 'bulkStatus']);
        Route::get('/{id}', [StoreServiceController::class, 'show']);
        Route::patch('/{id}', [StoreServiceController::class, 'update']);
        Route::put('/{id}', [StoreServiceController::class, 'update']);
        Route::patch('/{id}/status', [StoreServiceController::class, 'updateStatus']);
        Route::patch('/{id}/toggle-status', [StoreServiceController::class, 'toggleStatus']);
        Route::delete('/{id}', [StoreServiceController::class, 'destroy']);
    });

    // Store Admin Courier Management (FR-COURIER-02, US-STORE-04)
    Route::prefix('store-admin/couriers')->group(function () {
        Route::get('/', [StoreCourierController::class, 'index']);
        Route::post('/recruit/{id}', [StoreCourierController::class, 'recruit']);
        Route::post('/terminate/{id}', [StoreCourierController::class, 'terminate']);
        Route::post('/reject/{id}', [StoreCourierController::class, 'reject']);
    });

    // Store Admin Complaints Management (FR-COM-03)
    Route::prefix('store-admin/complaints')->group(function () {
        Route::get('/', [ComplaintController::class, 'storeAdminIndex']);
        Route::get('/{id}', [ComplaintController::class, 'show']);
        Route::patch('/{id}/status', [ComplaintController::class, 'updateStatus']);
    });

    // Store Admin Ratings & Reviews (FR-RATE-02)
    Route::prefix('store-admin/ratings')->group(function () {
        Route::get('/', [RatingController::class, 'storeAdminIndex']);
    });

    // Store Ledger & Financial Accounting (FR-PAY-11, US-STORE-08)
    Route::prefix('store-admin/ledger')->group(function () {
        Route::get('/', [StoreLedgerController::class, 'index']);
    });
    Route::get('/store/ledger', [StoreLedgerController::class, 'index']);


    // Human Chat Customer <-> Store (FR-CHAT, US-CHAT)
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

    // Courier Operational Tasks (FR-COURIER-03..06, US-COU-02..05, FR-PAY-07)
    Route::prefix('courier')->middleware('ensure_active_mode:courier')->group(function () {
        Route::get('/profile', [CourierTaskController::class, 'profile']);
        Route::get('/tasks', [CourierTaskController::class, 'tasks']);
        Route::get('/tasks/{id}', [CourierTaskController::class, 'show']);
        Route::post('/tasks/{id}/claim', [CourierTaskController::class, 'claim']);
        Route::post('/tasks/{id}/transition', [CourierTaskController::class, 'transition']);
        Route::post('/tasks/{id}/confirm-cod', [CourierTaskController::class, 'confirmCodPayment']);
        Route::post('/orders/{id}/confirm-cod', [CourierTaskController::class, 'confirmCodPayment']);
    });

    // Super Admin Suite (FR-ADMIN, US-ADMIN-01..08)
    Route::prefix('admin')->middleware('super_admin')->group(function () {
        // Auth Session & Logout
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);

        // Monitoring Dashboard (FR-ADMIN-07, FR-ADMIN-08)
        Route::get('/dashboard/metrics', [AdminDashboardController::class, 'metrics']);

        // Cross-Store Orders Monitoring (FR-ADMIN-ORDER)
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);

        // Platform Financial Transactions & Payments (FR-ADMIN-PAY)
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);

        // Store Approvals & Management (FR-ADMIN-02, FR-ADMIN-03)
        Route::get('/stores', [AdminStoreController::class, 'index']);
        Route::get('/stores/{id}', [AdminStoreController::class, 'show']);
        Route::post('/stores/{id}/approve', [AdminStoreController::class, 'approve']);
        Route::post('/stores/{id}/reject', [AdminStoreController::class, 'reject']);
        Route::post('/stores/{id}/suspend', [AdminStoreController::class, 'suspend']);
        Route::post('/stores/{id}/unsuspend', [AdminStoreController::class, 'unsuspend']);

        // User Suspension & Management (FR-ADMIN-04, FR-ADMIN-05)
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::patch('/users/{id}/status', [AdminUserController::class, 'updateStatus']);

        // Service Categories Master CRUD (FR-ADMIN-06)
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::get('/categories/{id}', [AdminCategoryController::class, 'show']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

        // Platform Settings & Commission (FR-PAY-10, US-ADMIN-04)
        Route::get('/platform-settings/commission', [PlatformSettingController::class, 'getCommission']);
        Route::patch('/platform-settings/commission', [PlatformSettingController::class, 'updateCommission']);

        // Audit Logs (FR-ADMIN-10, US-ADMIN-08)
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AdminAuditLogController::class, 'show']);
    });

    // Private Documents Streaming (Authorized Only)
    Route::prefix('documents')->group(function () {
        Route::get('/courier/ktp', [DocumentController::class, 'courierKtp']);
        Route::get('/store/{id}', [DocumentController::class, 'storeDocument']);
    });

    // Chatbot AI Assistant Sessions & Messages (FR-AI-01..08, US-CUS-10)
    Route::prefix('chatbot')->group(function () {
        Route::get('/sessions', [ChatbotController::class, 'index']);
        Route::post('/sessions', [ChatbotController::class, 'store'])->middleware('throttle:ai');
        Route::get('/sessions/{id}', [ChatbotController::class, 'show']);
        Route::post('/sessions/{id}/messages', [ChatbotController::class, 'sendMessage'])->middleware('throttle:ai');
        Route::delete('/sessions/{id}', [ChatbotController::class, 'destroy']);
    });
});



