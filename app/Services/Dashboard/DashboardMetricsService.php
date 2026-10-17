<?php

namespace App\Services\Dashboard;

use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\DashboardMetricCache;
use App\Models\Dispute;
use App\Models\FailedLoginAttempt;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 18 — Precompute service for dashboard metrics.
 *
 * Goals:
 *  - Tidak menghitung ulang agregat berat (GMV, completion rate, dll) setiap kali dashboard dibuka.
 *  - Store tetap terisolasi: scope "store:<id>" hanya lihat data store sendiri.
 *  - Platform admin/owner lihat scope "platform".
 *  - Cache di tabel dashboard_metric_caches dengan TTL 15 menit (jika dihitung on-demand).
 *
 * Dipanggil oleh:
 *  - DashboardController::xxx() -> cache miss -> recompute (lazy fallback)
 *  - Console command: dashboards:recompute-metrics (scheduled)
 */
class DashboardMetricsService
{
    public const TTL_SECONDS = 900; // 15 min

    /**
     * Get metric value, recompute if stale.
     */
    public function get(string $scope, string $key): mixed
    {
        $cache = DashboardMetricCache::where('scope', $scope)
            ->where('metric_key', $key)
            ->first();

        if ($cache && $cache->computed_at && $cache->computed_at->diffInSeconds(now()) < self::TTL_SECONDS) {
            return $cache->metric_value;
        }

        $value = $this->recompute($scope, $key);

        DashboardMetricCache::updateOrCreate(
            ['scope' => $scope, 'metric_key' => $key],
            ['metric_value' => $value, 'computed_at' => now()]
        );

        return $value;
    }

    /**
     * Force recompute all metrics for a scope.
     */
    public function recomputeScope(string $scope): void
    {
        $metrics = $this->getMetricKeysForScope($scope);
        foreach ($metrics as $key) {
            $value = $this->recompute($scope, $key);
            DashboardMetricCache::updateOrCreate(
                ['scope' => $scope, 'metric_key' => $key],
                ['metric_value' => $value, 'computed_at' => now()]
            );
        }
    }

    /**
     * Recompute all known scopes.
     */
    public function recomputeAll(): void
    {
        // platform scope
        $this->recomputeScope('platform');

        // store scopes
        Store::where('status', 'approved')->pluck('id')->each(function ($id) {
            $this->recomputeScope("store:{$id}");
        });

        // courier scopes
        Courier::pluck('id')->each(function ($id) {
            $this->recomputeScope("courier:{$id}");
        });
    }

    private function getMetricKeysForScope(string $scope): array
    {
        if ($scope === 'platform') {
            return [
                'gmv_today', 'gmv_month', 'gmv_total',
                'orders_today', 'orders_month', 'orders_in_progress', 'completion_rate',
                'stores_total', 'stores_active', 'stores_pending',
                'customers_total', 'customers_active_today',
                'couriers_total', 'couriers_available',
                'payments_today', 'cod_today', 'online_today',
                'refunds_pending', 'refunds_today',
                'disputes_open', 'disputes_resolved_today',
                'ai_calls_today', 'ai_cost_today', 'ai_tokens_today',
                'failed_logins_24h', 'audit_events_24h',
            ];
        }
        if (str_starts_with($scope, 'store:')) {
            return [
                'orders_today', 'orders_in_progress', 'orders_completed_month',
                'revenue_today', 'revenue_month', 'revenue_total',
                'cod_today', 'online_today',
                'refunds_pending', 'refunds_month',
                'avg_rating', 'review_count',
                'services_active', 'staff_count', 'customers_unique',
                'settlement_pending_amount',
            ];
        }
        if (str_starts_with($scope, 'courier:')) {
            return [
                'tasks_active', 'tasks_today_completed', 'tasks_lifetime',
                'earnings_today', 'earnings_month', 'earnings_lifetime',
                'avg_rating', 'is_available',
            ];
        }
        return [];
    }

    private function recompute(string $scope, string $key): mixed
    {
        if ($scope === 'platform') {
            return $this->recomputePlatform($key);
        }
        if (str_starts_with($scope, 'store:')) {
            $storeId = (int) substr($scope, 6);
            return $this->recomputeStore($storeId, $key);
        }
        if (str_starts_with($scope, 'courier:')) {
            $courierId = (int) substr($scope, 8);
            return $this->recomputeCourier($courierId, $key);
        }
        return null;
    }

    // ============ PLATFORM ============

    private function recomputePlatform(string $key): mixed
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $now = Carbon::now();

        return match ($key) {
            'gmv_today' => (float) Payment::where('status', 'paid')->whereDate('paid_at', $today)->sum('amount'),
            'gmv_month' => (float) Payment::where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount'),
            'gmv_total' => (float) Payment::where('status', 'paid')->sum('amount'),
            'orders_today' => Order::whereDate('created_at', $today)->count(),
            'orders_month' => Order::where('created_at', '>=', $monthStart)->count(),
            'orders_in_progress' => Order::whereNotIn('status', ['completed', 'cancelled', 'delivered'])->count(),
            'completion_rate' => $this->safeRatio(
                Order::where('status', 'completed')->count(),
                Order::whereNotNull('status')->count()
            ),
            'stores_total' => Store::count(),
            'stores_active' => Store::where('is_active', true)->count(),
            'stores_pending' => Store::whereIn('status', ['submitted', 'resubmitted', 'under_review'])->count(),
            'customers_total' => User::whereHas('roles', fn($q) => $q->where('name', 'customer'))->count(),
            'customers_active_today' => Order::whereDate('created_at', $today)->distinct('customer_id')->count('customer_id'),
            'couriers_total' => Courier::count(),
            'couriers_available' => Courier::where('is_available', true)->where('is_verified', true)->count(),
            'payments_today' => (float) Payment::where('status', 'paid')->whereDate('paid_at', $today)->sum('amount'),
            'cod_today' => (float) Payment::where('status', 'paid')->where('payment_method', 'cod')->whereDate('paid_at', $today)->sum('amount'),
            'online_today' => (float) Payment::where('status', 'paid')->where('payment_method', '!=', 'cod')->whereDate('paid_at', $today)->sum('amount'),
            'refunds_pending' => Refund::whereIn('status', ['requested', 'processing'])->count(),
            'refunds_today' => (float) Refund::whereIn('status', ['approved', 'completed'])->whereDate('updated_at', $today)->sum('amount'),
            'disputes_open' => Dispute::whereIn('status', ['open', 'investigating', 'info_requested'])->count(),
            'disputes_resolved_today' => Dispute::where('status', 'resolved')->whereDate('updated_at', $today)->count(),
            'ai_calls_today' => AiUsageLog::whereDate('created_at', $today)->count(),
            'ai_cost_today' => (float) AiUsageLog::whereDate('created_at', $today)->sum('estimated_cost_usd'),
            'ai_tokens_today' => (int) AiUsageLog::whereDate('created_at', $today)->sum('total_tokens'),
            'failed_logins_24h' => FailedLoginAttempt::where('attempted_at', '>=', $now->copy()->subDay())->count(),
            'audit_events_24h' => AuditLog::where('created_at', '>=', $now->copy()->subDay())->count(),
            default => null,
        };
    }

    // ============ STORE ============

    private function recomputeStore(int $storeId, string $key): mixed
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $orderBase = fn() => Order::where('store_id', $storeId);

        return match ($key) {
            'orders_today' => $orderBase()->whereDate('created_at', $today)->count(),
            'orders_in_progress' => $orderBase()->whereNotIn('status', ['completed', 'cancelled', 'delivered'])->count(),
            'orders_completed_month' => $orderBase()->where('status', 'completed')->where('created_at', '>=', $monthStart)->count(),
            'revenue_today' => (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderBase()->pluck('id'))
                ->whereDate('paid_at', $today)->sum('amount'),
            'revenue_month' => (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderBase()->pluck('id'))
                ->where('paid_at', '>=', $monthStart)->sum('amount'),
            'revenue_total' => (float) Payment::where('status', 'paid')
                ->whereIn('order_id', $orderBase()->pluck('id'))->sum('amount'),
            'cod_today' => (float) Payment::where('status', 'paid')->where('payment_method', 'cod')
                ->whereIn('order_id', $orderBase()->pluck('id'))->whereDate('paid_at', $today)->sum('amount'),
            'online_today' => (float) Payment::where('status', 'paid')->where('payment_method', '!=', 'cod')
                ->whereIn('order_id', $orderBase()->pluck('id'))->whereDate('paid_at', $today)->sum('amount'),
            'refunds_pending' => Refund::whereIn('status', ['requested', 'processing'])
                ->whereIn('order_id', $orderBase()->pluck('id'))->count(),
            'refunds_month' => (float) Refund::whereIn('status', ['approved', 'completed'])
                ->whereIn('order_id', $orderBase()->pluck('id'))
                ->where('updated_at', '>=', $monthStart)->sum('amount'),
            'avg_rating' => (float) Review::whereIn('order_id', $orderBase()->pluck('id'))->where('is_visible', true)->avg('rating') ?? 0,
            'review_count' => Review::whereIn('order_id', $orderBase()->pluck('id'))->where('is_visible', true)->count(),
            'services_active' => DB::table('service_prices')->where('store_id', $storeId)->where('is_active', true)->count(),
            'staff_count' => DB::table('store_staff')->where('store_id', $storeId)->where('is_active', true)->count(),
            'customers_unique' => $orderBase()->distinct('customer_id')->count('customer_id'),
            'settlement_pending_amount' => (float) DB::table('settlement_periods')
                ->where('store_id', $storeId)
                ->whereIn('status', ['pending', 'generated'])
                ->sum('net_amount'),
            default => null,
        };
    }

    // ============ COURIER ============

    private function recomputeCourier(int $courierId, string $key): mixed
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $taskBase = fn() => CourierTask::where('courier_id', $courierId);

        return match ($key) {
            'tasks_active' => $taskBase()->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])->count(),
            'tasks_today_completed' => $taskBase()->where('status', 'delivered')->whereDate('completed_at', $today)->count(),
            'tasks_lifetime' => $taskBase()->where('status', 'delivered')->count(),
            'earnings_today' => $this->courierEarnings($courierId, $today, null),
            'earnings_month' => $this->courierEarnings($courierId, null, $monthStart),
            'earnings_lifetime' => $this->courierEarnings($courierId, null, null),
            'avg_rating' => (float) Review::whereIn('order_id', $taskBase()->pluck('order_id'))->where('is_visible', true)->avg('rating') ?? 0,
            'is_available' => (bool) optional(Courier::find($courierId))->is_available,
            default => null,
        };
    }

    /**
     * Estimate courier earnings from completed tasks' order totals.
     * Real commission split would use settlement_items; this is an approximation for dashboard.
     */
    private function courierEarnings(int $courierId, ?Carbon $onDate, ?Carbon $since): float
    {
        $query = CourierTask::where('courier_id', $courierId)->where('status', 'delivered');
        if ($onDate) {
            $query->whereDate('completed_at', $onDate);
        } elseif ($since) {
            $query->where('completed_at', '>=', $since);
        }
        $orderIds = $query->pluck('order_id');
        if ($orderIds->isEmpty()) {
            return 0.0;
        }
        // Default courier fee = 5000 per delivered task (consistent with Order model)
        return (float) ($orderIds->count() * 5000);
    }

    private function safeRatio(int|float $numerator, int|float $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }
        return round($numerator / $denominator, 4);
    }
}
