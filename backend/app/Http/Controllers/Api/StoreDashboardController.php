<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Store;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class StoreDashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get store dashboard metrics and recent orders.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();

        // Resolve store
        $storeId = $request->query('store_id');
        $store = $storeId
            ? Store::where('id', $storeId)->where('owner_user_id', $user->id)->first()
            : Store::where('owner_user_id', $user->id)->orderBy('approved_at', 'asc')->first();

        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_FOUND',
                'Anda belum memiliki toko terdaftar atau toko tidak ditemukan.',
                null,
                404
            );
        }

        $todayStart = Carbon::today()->startOfDay();

        // Order counts by operational state
        $ordersNew = Order::where('store_id', $store->id)
            ->whereIn('status', ['PENDING_PICKUP', 'PICKUP_ASSIGNED', 'PICKED_UP', 'RECEIVED_BY_STORE', 'WAITING_CUSTOMER_APPROVAL'])
            ->count();

        $ordersProcessing = Order::where('store_id', $store->id)
            ->where('status', 'PROCESSING')
            ->count();

        $ordersReady = Order::where('store_id', $store->id)
            ->where('status', 'READY_FOR_DELIVERY')
            ->count();

        $ordersCompletedToday = Order::where('store_id', $store->id)
            ->where('status', 'COMPLETED')
            ->where(function ($q) use ($todayStart) {
                $q->where('completed_at', '>=', $todayStart)
                  ->orWhere(function ($sub) use ($todayStart) {
                      $sub->whereNull('completed_at')->where('updated_at', '>=', $todayStart);
                  });
            })
            ->count();

        // Revenue today (sum of completed or paid orders today)
        $revenueToday = (int) Order::where('store_id', $store->id)
            ->where(function ($q) use ($todayStart) {
                $q->where(function ($paid) use ($todayStart) {
                    $paid->where('payment_status', 'PAID')
                         ->where('updated_at', '>=', $todayStart);
                })->orWhere(function ($comp) use ($todayStart) {
                    $comp->where('status', 'COMPLETED')
                         ->where('updated_at', '>=', $todayStart);
                });
            })
            ->sum('final_amount');

        // Recent 10 orders
        $recentOrders = Order::where('store_id', $store->id)
            ->with(['customer:id,name,phone'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn (Order $ord) => [
                'id' => $ord->id,
                'order_number' => $ord->order_number,
                'customer_name' => $ord->customer?->name ?? 'Pelanggan',
                'status' => $ord->status,
                'payment_status' => $ord->payment_status,
                'estimated_amount' => (int) $ord->estimated_amount,
                'final_amount' => $ord->final_amount !== null ? (int) $ord->final_amount : null,
                'created_at' => $ord->created_at?->toIso8601String(),
            ]);

        // Services statistics
        $servicesTotal = $store->services()->count();
        $servicesActive = $store->services()->where('is_active', true)->count();

        // Owned stores list for switcher
        $allStores = Store::where('owner_user_id', $user->id)
            ->orderBy('approved_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get(['id', 'name', 'approval_status', 'operational_status', 'rejection_reason', 'approved_at']);

        return $this->successResponse(
            data: [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'address_text' => $store->address_text,
                    'approval_status' => $store->approval_status,
                    'operational_status' => $store->operational_status,
                    'rejection_reason' => $store->rejection_reason,
                    'approved_at' => $store->approved_at?->toIso8601String(),
                    'services_total' => $servicesTotal,
                    'services_active' => $servicesActive,
                ],
                'my_stores' => $allStores,
                'metrics' => [
                    'orders_new' => $ordersNew,
                    'orders_processing' => $ordersProcessing,
                    'orders_ready_for_delivery' => $ordersReady,
                    'orders_completed_today' => $ordersCompletedToday,
                    'revenue_today' => $revenueToday,
                ],
                'recent_orders' => $recentOrders,
            ]
        );
    }

    /**
     * Toggle or set operational status (active / inactive) by Store Admin.
     */
    public function toggleOperationalStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $storeId = $request->input('store_id');
        $store = $storeId
            ? Store::where('id', $storeId)->where('owner_user_id', $user->id)->first()
            : Store::where('owner_user_id', $user->id)->orderBy('approved_at', 'asc')->first();

        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_FOUND',
                'Toko tidak ditemukan atau bukan milik Anda.',
                null,
                404
            );
        }

        if ($store->approval_status !== 'approved') {
            return $this->errorResponse(
                'STORE_NOT_APPROVED',
                'Status operasional hanya dapat diubah setelah toko disetujui.',
                null,
                422
            );
        }

        if ($store->operational_status === 'suspended') {
            return $this->errorResponse(
                'STORE_SUSPENDED',
                'Toko Anda sedang ditangguhkan oleh Admin Platform dan tidak dapat dibuka secara mandiri.',
                ['reason' => $store->rejection_reason],
                403
            );
        }

        $oldStatus = $store->operational_status;

        if ($request->filled('operational_status')) {
            $newStatus = $request->input('operational_status');
            if (!in_array($newStatus, ['active', 'inactive'])) {
                return $this->errorResponse('VALIDATION_ERROR', 'Status operasional harus active atau inactive.', null, 422);
            }
        } else {
            $newStatus = $oldStatus === 'active' ? 'inactive' : 'active';
        }

        $store->update(['operational_status' => $newStatus]);

        AuditLog::record(
            actor: $user,
            action: 'UPDATE_STORE_OPERATIONAL_STATUS',
            entityType: 'stores',
            entityId: $store->id,
            metadata: [
                'previous_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
            ip: $request->ip()
        );

        $statusText = $newStatus === 'active' ? 'dibuka' : 'ditutup sementara';

        return $this->successResponse(
            data: [
                'store_id' => $store->id,
                'operational_status' => $newStatus,
            ],
            meta: [
                'message' => "Toko '{$store->name}' berhasil {$statusText}.",
            ]
        );
    }

    /**
     * Get aggregate sidebar counts for store admin (FR-STORE-SIDEBAR-BADGE).
     */
    public function sidebarCounts(Request $request): JsonResponse
    {
        $user = $request->user();

        // Resolve store with store scope
        $storeId = $request->query('store_id') ?: $request->header('X-Store-Id');
        $store = $storeId
            ? Store::where('id', $storeId)->where('owner_user_id', $user->id)->first()
            : Store::where('owner_user_id', $user->id)->orderBy('approved_at', 'asc')->first();

        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_FOUND',
                'Anda belum memiliki toko terdaftar atau toko tidak ditemukan.',
                null,
                404
            );
        }

        // 1. Pending courier candidates applying for this specific store (profile or assignment pending)
        $pendingProfiles = \App\Models\CourierProfile::where(function ($q) use ($store) {
            $q->where('store_id', $store->id)
              ->orWhereNull('store_id');
        })
            ->where('verification_status', 'pending')
            ->whereDoesntHave('storeAssignments', fn ($q) => $q->where('status', 'active'))
            ->count();

        $pendingAssignments = \App\Models\CourierStoreAssignment::where('store_id', $store->id)
            ->where('status', 'pending')
            ->count();

        $pendingCouriers = max($pendingProfiles, $pendingAssignments);

        // 2. New orders waiting for store action (status PENDING_PICKUP)
        $pesananBaru = Order::where('store_id', $store->id)
            ->where('status', 'PENDING_PICKUP')
            ->count();

        // 3. Unread chat messages for store conversations
        $storeConvIds = \App\Models\Conversation::where('store_id', $store->id)->pluck('id');
        $unreadChat = \App\Models\ChatMessage::whereIn('conversation_id', $storeConvIds)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->successResponse([
            'pelamar_kurir_pending' => $pendingCouriers,
            'pesanan_baru' => $pesananBaru,
            'unread_chat' => $unreadChat,
            'store_id' => $store->id,
        ]);
    }
}
