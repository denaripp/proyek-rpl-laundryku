<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 18 — Store dashboard (API).
 *
 * Beda dari web DashboardController::storeDashboard() — endpoint ini
 * mengembalikan JSON mentah untuk frontend SPA / mobile.
 *
 * Prinsip:
 *  - Tenant isolation: actor HARUS owner/staff dari store yg direquest.
 *    Cross-store access => 403 (lihat test DashboardTenantIsolationTest).
 *  - Metric berat via DashboardMetricsService (cached 15 menit).
 *  - Settlement / refund amount yang sensitif difilter sesuai role
 *    (store_owner lihat semua untuk store sendiri, store_staff tidak
 *    lihat settlement amount kecuali permission eksplisit).
 */
class StoreDashboardController extends Controller
{
    public function __construct(protected DashboardMetricsService $metrics) {}

    /**
     * GET /api/store-owner/dashboard?store_id={id}
     * Tanpa query param, pakai store pertama yg dimiliki/distaff user.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo('view_store_dashboard'), 403, 'Forbidden.');

        $store = $this->resolveStoreForUser($request, $user);
        abort_unless($store, 404, 'No store accessible by this user.');

        $scope = "store:{$store->id}";
        $m = fn(string $k) => $this->metrics->get($scope, $k);

        // Top customers (5 teratas by order count, scoped ke store)
        $topCustomers = Order::where('store_id', $store->id)
            ->select('customer_id',
                DB::raw('count(*) as total_orders'),
                DB::raw('sum(total_amount) as total_spent'))
            ->groupBy('customer_id')
            ->with('customer:id,name,email')
            ->orderByDesc('total_orders')
            ->limit(5)
            ->get();

        // COD vs online split (30 hari terakhir)
        $paymentBreakdown = Payment::where('status', 'paid')
            ->whereIn('order_id', Order::where('store_id', $store->id)->pluck('id'))
            ->where('paid_at', '>=', Carbon::now()->subDays(30))
            ->select('payment_method', DB::raw('count(*) as cnt'), DB::raw('sum(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // Recent orders (10 terakhir)
        $recentOrders = Order::where('store_id', $store->id)
            ->with('customer:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'order_number', 'status', 'total_amount', 'customer_id', 'created_at']);

        // Menu sesuai permission store_owner/staff
        $menu = $this->buildStoreMenu($user);

        return response()->json([
            'store' => $store->only(['id', 'name', 'slug', 'status', 'is_active']),
            'menu' => $menu,
            'metrics' => [
                'orders_today' => $m('orders_today'),
                'orders_in_progress' => $m('orders_in_progress'),
                'orders_completed_month' => $m('orders_completed_month'),
                'revenue_today' => $m('revenue_today'),
                'revenue_month' => $m('revenue_month'),
                'revenue_total' => $m('revenue_total'),
                'cod_today' => $m('cod_today'),
                'online_today' => $m('online_today'),
                'refunds_pending' => $m('refunds_pending'),
                'refunds_month' => $m('refunds_month'),
                'avg_rating' => $m('avg_rating'),
                'review_count' => $m('review_count'),
                'services_active' => $m('services_active'),
                'staff_count' => $m('staff_count'),
                'customers_unique' => $m('customers_unique'),
                // Settlement amount HANYA untuk store_owner.
                // store_staff tidak boleh lihat nominal settlement (PRD: least privilege).
                'settlement_pending_amount' => $user->hasRole('store_owner')
                    ? $m('settlement_pending_amount')
                    : null,
            ],
            'top_customers' => $topCustomers,
            'payment_breakdown' => $paymentBreakdown,
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * GET /api/store-owner/dashboard/services
     * Daftar layanan aktif store (untuk menu "Services & Pricing").
     */
    public function services(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo('view_store_dashboard'), 403, 'Forbidden.');

        $store = $this->resolveStoreForUser($request, $user);
        abort_unless($store, 404);

        $services = ServicePrice::where('store_id', $store->id)
            ->with('service:id,name')
            ->get(['id', 'service_id', 'price_per_unit', 'unit', 'is_active']);

        return response()->json([
            'store_id' => $store->id,
            'services' => $services,
            'count' => $services->count(),
            'active_count' => $services->where('is_active', true)->count(),
        ]);
    }

    /**
     * GET /api/store-owner/dashboard/staff
     * Daftar staff + courier store.
     */
    public function staff(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo('view_store_dashboard'), 403, 'Forbidden.');

        $store = $this->resolveStoreForUser($request, $user);
        abort_unless($store, 404);

        $staff = StoreStaff::where('store_id', $store->id)
            ->where('is_active', true)
            ->with('user:id,name,email')
            ->get(['id', 'user_id', 'permissions', 'is_active', 'invited_at', 'accepted_at']);

        return response()->json([
            'store_id' => $store->id,
            'staff' => $staff,
            'count' => $staff->count(),
        ]);
    }

    /**
     * GET /api/store-owner/dashboard/couriers
     * Daftar courier yang pernah/boleh handle order store.
     */
    public function couriers(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo('view_store_dashboard'), 403, 'Forbidden.');

        $store = $this->resolveStoreForUser($request, $user);
        abort_unless($store, 404);

        // Untuk MVP: tampilkan semua courier yg verified + available.
        // Real impl: join ke tabel store_courier pivot (jika ada).
        $couriers = \App\Models\Courier::where('is_verified', true)
            ->get(['id', 'user_id', 'is_available', 'vehicle_type', 'average_rating']);

        return response()->json([
            'store_id' => $store->id,
            'couriers' => $couriers,
            'count' => $couriers->count(),
        ]);
    }

    /**
     * GET /api/store-owner/dashboard/reports?from=&to=
     * Report revenue/COD/online/refund dalam window.
     */
    public function reports(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo('view_store_dashboard'), 403, 'Forbidden.');

        $store = $this->resolveStoreForUser($request, $user);
        abort_unless($store, 404);

        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $orderIds = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        $revenue = Payment::where('status', 'paid')
            ->whereIn('order_id', $orderIds)
            ->whereBetween('paid_at', [$from, $to])
            ->select(
                DB::raw("sum(case when payment_method = 'cod' then amount else 0 end) as cod"),
                DB::raw("sum(case when payment_method != 'cod' then amount else 0 end) as online"),
                DB::raw('sum(amount) as total')
            )
            ->first();

        $refundTotal = (float) Refund::whereIn('order_id', $orderIds)
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('updated_at', [$from, $to])
            ->sum('amount');

        $orderCount = $orderIds->count();
        $completedCount = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'completed')
            ->count();

        $rating = (float) (Review::whereIn('order_id', $orderIds)
            ->where('is_visible', true)
            ->avg('rating') ?? 0);

        return response()->json([
            'store_id' => $store->id,
            'window' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'revenue_cod' => (float) ($revenue->cod ?? 0),
            'revenue_online' => (float) ($revenue->online ?? 0),
            'revenue_total' => (float) ($revenue->total ?? 0),
            'refund_total' => $refundTotal,
            'order_count' => $orderCount,
            'completed_count' => $completedCount,
            'completion_rate' => $orderCount > 0 ? round($completedCount / $orderCount, 4) : 0.0,
            'avg_rating' => round($rating, 2),
        ]);
    }

    // ============== HELPERS ==============

    /**
     * Resolve store for the current user. If ?store_id= given, validate
     * the user has access to it (owner/staff). Else use first owned/staffed.
     */
    protected function resolveStoreForUser(Request $request, User $user): ?Store
    {
        $requestedId = $request->integer('store_id');

        if ($requestedId > 0) {
            // Explicit store requested — verify tenant access.
            $hasAccess = $user->ownedStores()->where('id', $requestedId)->exists()
                || $user->storeStaff()->where('store_id', $requestedId)->where('is_active', true)->exists();

            if (!$hasAccess) {
                // Return a non-null-but-forbidden store so caller can 403 cleanly.
                // We use a sentinel: return Store::find but caller should 403.
                // For clarity, just abort here.
                abort(403, 'You do not have access to this store.');
            }

            return Store::find($requestedId);
        }

        // Fallback: owned -> staffed
        $owned = $user->ownedStores()->first();
        if ($owned) {
            return $owned;
        }
        $staff = StoreStaff::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('store')
            ->first();
        return $staff?->store;
    }

    /**
     * Build menu visible to this store user (least privilege):
     *  - store_staff tidak lihat settlement
     *  - semua role store boleh lihat services/staff/courier/reports
     */
    protected function buildStoreMenu(User $user): array
    {
        $all = [
            ['key' => 'orders',    'label' => 'Orders',    'perm' => 'view_order'],
            ['key' => 'services',  'label' => 'Services',  'perm' => 'update_store'],
            ['key' => 'staff',     'label' => 'Staff',     'perm' => 'update_store'],
            ['key' => 'couriers',  'label' => 'Couriers',  'perm' => 'update_store'],
            ['key' => 'reports',   'label' => 'Reports',   'perm' => 'view_store_dashboard'],
            ['key' => 'settlement', 'label' => 'Settlement', 'perm' => 'view_any_settlement'],
        ];

        return array_values(array_filter($all, fn($i) => $user->hasPermissionTo($i['perm'])));
    }
}