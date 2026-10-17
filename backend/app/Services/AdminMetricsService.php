<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;

class AdminMetricsService
{
    /**
     * Compute comprehensive, real-time admin monitoring metrics.
     * All period boundaries are evaluated in Asia/Jakarta timezone.
     */
    public static function getMetrics(string $period = 'all_time', ?string $startDate = null, ?string $endDate = null): array
    {
        $tz = 'Asia/Jakarta';
        $now = Carbon::now($tz);

        $orderQuery = Order::query();

        // 1. Apply strict period boundaries in Asia/Jakarta timezone
        if ($period === 'today') {
            $orderQuery->whereBetween('created_at', [
                $now->copy()->startOfDay()->setTimezone('UTC'),
                $now->copy()->endOfDay()->setTimezone('UTC'),
            ]);
        } elseif ($period === 'this_week') {
            $orderQuery->whereBetween('created_at', [
                $now->copy()->startOfWeek()->setTimezone('UTC'),
                $now->copy()->endOfWeek()->setTimezone('UTC'),
            ]);
        } elseif ($period === 'this_month') {
            $orderQuery->whereBetween('created_at', [
                $now->copy()->startOfMonth()->setTimezone('UTC'),
                $now->copy()->endOfMonth()->setTimezone('UTC'),
            ]);
        } elseif ($period === 'this_year') {
            $orderQuery->whereBetween('created_at', [
                $now->copy()->startOfYear()->setTimezone('UTC'),
                $now->copy()->endOfYear()->setTimezone('UTC'),
            ]);
        } elseif ($period === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate, $tz)->startOfDay()->setTimezone('UTC');
            $end = Carbon::parse($endDate, $tz)->endOfDay()->setTimezone('UTC');
            $orderQuery->whereBetween('created_at', [$start, $end]);
        }

        // Total orders in period
        $totalOrders = (clone $orderQuery)->count();

        // Completed orders in period
        $completedOrdersQuery = (clone $orderQuery)->where('status', 'COMPLETED');
        $completedOrdersCount = (clone $completedOrdersQuery)->count();

        // GMV: Total final_amount of COMPLETED orders (Rupiah integer)
        $totalGmv = (int) ((clone $completedOrdersQuery)->sum('final_amount') ?: 0);

        // Platform Commission: Sum of stored commission_amount snapshot in COMPLETED orders
        $totalPlatformRevenue = (int) ((clone $completedOrdersQuery)->sum('commission_amount') ?: 0);

        // Raw orders breakdown by status
        $rawOrdersByStatus = (clone $orderQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Standardized status distribution categories
        $statusDistribution = [
            'waiting_pickup' => ($rawOrdersByStatus['PENDING_PICKUP'] ?? 0) + ($rawOrdersByStatus['PICKUP_ASSIGNED'] ?? 0),
            'processing' => ($rawOrdersByStatus['PICKED_UP'] ?? 0) +
                            ($rawOrdersByStatus['AT_STORE'] ?? 0) +
                            ($rawOrdersByStatus['WEIGHED'] ?? 0) +
                            ($rawOrdersByStatus['AWAITING_PRICE_APPROVAL'] ?? 0) +
                            ($rawOrdersByStatus['PRICE_APPROVED'] ?? 0) +
                            ($rawOrdersByStatus['AWAITING_PAYMENT'] ?? 0) +
                            ($rawOrdersByStatus['PAID'] ?? 0) +
                            ($rawOrdersByStatus['PROCESSING'] ?? 0) +
                            ($rawOrdersByStatus['READY_FOR_DELIVERY'] ?? 0) +
                            ($rawOrdersByStatus['DELIVERY_ASSIGNED'] ?? 0) +
                            ($rawOrdersByStatus['OUT_FOR_DELIVERY'] ?? 0),
            'completed' => $rawOrdersByStatus['COMPLETED'] ?? 0,
            'cancelled' => ($rawOrdersByStatus['CANCELLED'] ?? 0) +
                           ($rawOrdersByStatus['PAYMENT_EXPIRED'] ?? 0) +
                           ($rawOrdersByStatus['PAYMENT_FAILED'] ?? 0),
        ];

        // Store counts (all time)
        $totalStores = Store::count();
        $activeStores = Store::where('approval_status', 'approved')->where('operational_status', 'active')->count();
        $pendingStores = Store::where('approval_status', 'pending')->count();
        $rejectedStores = Store::where('approval_status', 'rejected')->count();

        // User counts (all time)
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $suspendedUsers = User::whereIn('status', ['suspended', 'banned'])->count();

        // Users count by role
        $customersCount = User::whereHas('roles', fn ($q) => $q->where('code', 'customer'))->count();
        $storeAdminsCount = User::whereHas('roles', fn ($q) => $q->where('code', 'store_admin'))->count();
        $couriersCount = User::whereHas('roles', fn ($q) => $q->where('code', 'courier'))->count();
        $superAdminsCount = User::whereHas('roles', fn ($q) => $q->where('code', 'super_admin'))->count();

        // Recent Audit Logs
        $recentAuditLogs = AuditLog::with('actor')
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'actor_name' => $log->actor?->name ?? 'System',
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return [
            'period' => $period,
            'timezone' => $tz,
            'metrics' => [
                'total_gmv' => $totalGmv,
                'total_platform_revenue' => $totalPlatformRevenue,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrdersCount,
                'active_stores' => $activeStores,
                'pending_stores' => $pendingStores,
                'total_stores' => $totalStores,
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'suspended_users' => $suspendedUsers,
            ],
            'status_distribution' => $statusDistribution,
            'orders_by_status' => $rawOrdersByStatus,
            'users_by_role' => [
                'customer' => $customersCount,
                'store_admin' => $storeAdminsCount,
                'courier' => $couriersCount,
                'super_admin' => $superAdminsCount,
            ],
            'recent_logs' => $recentAuditLogs,
        ];
    }
}
