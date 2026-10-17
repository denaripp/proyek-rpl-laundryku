<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CommissionSetting;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Dispute;
use App\Models\FailedLoginAttempt;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Tahap 18 — Internal dashboards (Store, Courier, Platform Admin, Platform Owner).
 *
 * Prinsip:
 *  - View = presentasi saja. Business logic di service (Order/Payment/Refund/Settlement).
 *  - Data aggregate dari DashboardMetricsService (cached 15 menit, recompute setiap 10 menit via scheduler).
 *  - Tenant isolation: store tidak bisa lihat data store lain (scope "store:<id>" dipakai).
 *  - Platform admin lihat menu sesuai permission-nya. Platform owner lihat semua.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected DashboardMetricsService $metrics,
        protected StoreService $storeService
    ) {}

    /**
     * Auto-redirect /dashboard ke dashboard sesuai role.
     */
    public function root()
    {
        $user = Auth::guard('admin')->user()
            ?: Auth::guard('mitra')->user()
            ?: Auth::guard('kurir')->user()
            ?: Auth::guard('web')->user()
            ?: Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }
        if ($user->hasPermissionTo('manage_platform')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->hasRole('platform_admin')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->hasRole('store_owner') || $user->hasRole('store_staff') || $user->ownedStores()->exists()) {
            $store = $this->resolveStore($user);
            if ($store) {
                if ($store->isApproved()) {
                    return redirect()->route('store.dashboard');
                }
                return redirect()->route('store.onboard');
            }
            return redirect()->route('store.onboard');
        }
        if ($user->hasRole('courier')) {
            return redirect()->route('courier.dashboard');
        }
        return redirect()->route('customer.dashboard');
    }

    // =================== STORE DASHBOARD ===================

    public function storeDashboard(Request $request)
    {
        $user = Auth::guard('mitra')->user() ?: Auth::user();
        $store = $this->resolveStore($user);
        abort_unless($store, 403, 'No store associated with user.');

        if (!$store->isApproved()) {
            return redirect()->route('store.onboard');
        }

        $scope = "store:{$store->id}";
        $m = fn($k) => $this->metrics->get($scope, $k);

        // Recent orders (status update)
        $recentOrders = Order::where('store_id', $store->id)
            ->with(['customer:id,name,phone', 'statusHistory' => fn($q) => $q->latest()->limit(1)])
            ->latest()
            ->limit(10)
            ->get();

        // Active orders (status not in terminal)
        $activeOrders = Order::where('store_id', $store->id)
            ->whereNotIn('status', ['completed', 'cancelled', 'delivered'])
            ->with('customer:id,name')
            ->latest()
            ->limit(10)
            ->get();

        // Top customers by order count
        $topCustomers = Order::where('store_id', $store->id)
            ->select('customer_id', DB::raw('count(*) as total_orders'), DB::raw('sum(total_amount) as total_spent'))
            ->groupBy('customer_id')
            ->with('customer:id,name,email')
            ->orderByDesc('total_orders')
            ->limit(5)
            ->get();

        // COD vs online split
        $paymentMethodBreakdown = Payment::where('status', 'paid')
            ->whereIn('order_id', Order::where('store_id', $store->id)->pluck('id'))
            ->where('paid_at', '>=', Carbon::now()->subDays(30))
            ->select('payment_method', DB::raw('count(*) as cnt'), DB::raw('sum(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        return view('dashboards.store', [
            'store' => $store,
            'metrics' => collect([
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
                'settlement_pending_amount' => $m('settlement_pending_amount'),
            ]),
            'recentOrders' => $recentOrders,
            'activeOrders' => $activeOrders,
            'topCustomers' => $topCustomers,
            'paymentMethodBreakdown' => $paymentMethodBreakdown,
        ]);
    }

    // =================== COURIER DASHBOARD ===================

    public function courierDashboard(Request $request)
    {
        $user = Auth::guard('kurir')->user() ?: Auth::user();
        abort_unless($user->hasPermissionTo('view_courier_dashboard'), 403);
        $courier = Courier::where('user_id', $user->id)->first();
        abort_unless($courier, 404, 'Courier profile not found.');

        $scope = "courier:{$courier->id}";
        $m = fn($k) => $this->metrics->get($scope, $k);

        $activeTasks = CourierTask::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'accepted', 'arrived', 'picked_up'])
            ->with(['order.store:id,name', 'order.customer:id,name'])
            ->latest()
            ->get();

        $recentTasks = CourierTask::where('courier_id', $courier->id)
            ->with(['order.store:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        return view('dashboards.courier', [
            'courier' => $courier,
            'metrics' => collect([
                'tasks_active' => $m('tasks_active'),
                'tasks_today_completed' => $m('tasks_today_completed'),
                'tasks_lifetime' => $m('tasks_lifetime'),
                'earnings_today' => $m('earnings_today'),
                'earnings_month' => $m('earnings_month'),
                'earnings_lifetime' => $m('earnings_lifetime'),
                'avg_rating' => $m('avg_rating'),
                'is_available' => $m('is_available'),
            ]),
            'activeTasks' => $activeTasks,
            'recentTasks' => $recentTasks,
        ]);
    }

    public function courierToggleAvailability(Request $request)
    {
        $user = Auth::guard('kurir')->user() ?: Auth::user();
        abort_unless($user->hasPermissionTo('view_courier_dashboard'), 403);
        $request->validate(['is_available' => 'required|boolean']);
        $courier = Courier::where('user_id', $user->id)->firstOrFail();
        $courier->is_available = $request->boolean('is_available');
        $courier->save();

        // Invalidate cache
        $this->metrics->recomputeScope("courier:{$courier->id}");

        return back()->with('success', 'Availability updated.');
    }

    // =================== PLATFORM ADMIN / OWNER ===================

    public function adminDashboard(Request $request)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        abort_unless($user->hasPermissionTo('view_admin_dashboard'), 403);

        $tab = $request->query('tab', 'overview');
        $m = fn($k) => $this->metrics->get('platform', $k);

        // Menu available berdasarkan permission
        $menu = $this->buildAdminMenu($user);

        // For owner only: commission snapshot
        $isOwner = $user->hasPermissionTo('manage_platform');
        $commissionTotal = $isOwner
            ? (float) CommissionSetting::where('is_active', true)->sum(DB::raw('commission_rate'))
            : null;
        $platformCommission = $isOwner
            ? (float) Payment::where('status', 'paid')
                ->where('paid_at', '>=', Carbon::now()->subDays(30))
                ->sum(DB::raw('amount * 0.10'))
            : null;

        // Dynamic Tab Queries
        $stores = $tab === 'stores' ? Store::with(['owner', 'application'])->latest()->paginate(15) : collect();
        $orders = $tab === 'orders' ? Order::with(['customer', 'store'])->latest()->paginate(15) : collect();
        $customers = $tab === 'customers' ? User::with('roles')->latest()->paginate(15) : collect();
        $couriers = $tab === 'couriers' ? Courier::with('user')->latest()->paginate(15) : collect();
        $payments = $tab === 'payments' ? Payment::with('order')->latest()->paginate(15) : collect();
        $refunds = $tab === 'refunds' ? Refund::with(['order', 'payment', 'requestedBy'])->latest()->paginate(15) : collect();
        $disputes = $tab === 'disputes' ? Dispute::with(['order', 'openedBy'])->latest()->paginate(15) : collect();
        $auditLogs = $tab === 'audit' ? AuditLog::with('actor:id,name')->latest()->paginate(25) : collect();
        $aiLogs = $tab === 'ai' ? \App\Models\AiUsageLog::with('user:id,name,email')->latest()->paginate(25) : collect();
        $commissionSettings = $tab === 'commission' ? CommissionSetting::latest()->get() : collect();

        // Recent audit + failed login (security snapshot for overview)
        $recentAudit = AuditLog::with('actor:id,name')->latest()->limit(10)->get();
        $failedLogins = FailedLoginAttempt::where('attempted_at', '>=', Carbon::now()->subDay())->latest('attempted_at')->limit(10)->get();

        return view('dashboards.admin', [
            'tab' => $tab,
            'metrics' => collect([
                'gmv_today' => $m('gmv_today'),
                'gmv_month' => $m('gmv_month'),
                'gmv_total' => $m('gmv_total'),
                'orders_today' => $m('orders_today'),
                'orders_month' => $m('orders_month'),
                'orders_in_progress' => $m('orders_in_progress'),
                'completion_rate' => $m('completion_rate'),
                'stores_total' => $m('stores_total'),
                'stores_active' => $m('stores_active'),
                'stores_pending' => $m('stores_pending'),
                'customers_total' => $m('customers_total'),
                'customers_active_today' => $m('customers_active_today'),
                'couriers_total' => $m('couriers_total'),
                'couriers_available' => $m('couriers_available'),
                'payments_today' => $m('payments_today'),
                'cod_today' => $m('cod_today'),
                'online_today' => $m('online_today'),
                'refunds_pending' => $m('refunds_pending'),
                'refunds_today' => $m('refunds_today'),
                'disputes_open' => $m('disputes_open'),
                'disputes_resolved_today' => $m('disputes_resolved_today'),
                'ai_calls_today' => $m('ai_calls_today'),
                'ai_cost_today' => $m('ai_cost_today'),
                'ai_tokens_today' => $m('ai_tokens_today'),
                'failed_logins_24h' => $m('failed_logins_24h'),
                'audit_events_24h' => $m('audit_events_24h'),
            ]),
            'menu' => $menu,
            'isOwner' => $isOwner,
            'commissionTotal' => $commissionTotal,
            'platformCommission' => $platformCommission,
            'recentAudit' => $recentAudit,
            'failedLogins' => $failedLogins,
            'stores' => $stores,
            'orders' => $orders,
            'customers' => $customers,
            'couriers' => $couriers,
            'payments' => $payments,
            'refunds' => $refunds,
            'disputes' => $disputes,
            'auditLogs' => $auditLogs,
            'aiLogs' => $aiLogs,
            'commissionSettings' => $commissionSettings,
        ]);
    }

    public function adminApproveStore(Request $request, Store $store)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        abort_unless($user->hasPermissionTo('manage_platform') || $user->hasPermissionTo('approve_store'), 403);

        try {
            if ($store->status === 'draft' || $store->status === 'submitted' || $store->status === 'resubmitted') {
                if ($store->status === 'draft') {
                    $this->storeService->submit($store);
                }
                $this->storeService->startReview($store, $user);
            }
            $this->storeService->approve($store, $user, $request->input('note', 'Approved via Admin Dashboard UI'));

            return back()->with('success', "Toko '{$store->name}' (ID: {$store->id}) BERHASIL DISETUJUI dan aktif!");
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal menyetujui toko: " . $e->getMessage());
        }
    }

    public function adminRejectStore(Request $request, Store $store)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        abort_unless($user->hasPermissionTo('manage_platform') || $user->hasPermissionTo('approve_store'), 403);
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            if (in_array($store->status, ['submitted', 'resubmitted', 'draft'])) {
                if ($store->status === 'draft') {
                    $this->storeService->submit($store);
                }
                $this->storeService->startReview($store, $user);
            }
            $this->storeService->reject($store, $user, $request->input('reason'));

            return back()->with('success', "Pendaftaran toko '{$store->name}' telah ditolak.");
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal menolak toko: " . $e->getMessage());
        }
    }

    public function adminSuspendStore(Request $request, Store $store)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        abort_unless($user->hasPermissionTo('manage_platform') || $user->hasPermissionTo('approve_store'), 403);
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->storeService->suspend($store, $user, $request->input('reason'));
            return back()->with('success', "Toko '{$store->name}' berhasil dinonaktifkan (suspend).");
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal menonaktifkan toko: " . $e->getMessage());
        }
    }

    public function adminReactivateStore(Request $request, Store $store)
    {
        $user = Auth::guard('admin')->user() ?: Auth::user();
        abort_unless($user->hasPermissionTo('manage_platform') || $user->hasPermissionTo('approve_store'), 403);

        try {
            $this->storeService->reactivate($store, $user, $request->input('note'));
            return back()->with('success', "Toko '{$store->name}' berhasil diaktifkan kembali.");
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal mengaktifkan toko: " . $e->getMessage());
        }
    }

    // =================== HELPERS ===================

    /**
     * Resolve store for store_owner / store_staff via user roles pivot (store_id).
     */
    protected function resolveStore(User $user): ?Store
    {
        // owner: via ownedStores
        $owned = $user->ownedStores()->first();
        if ($owned) {
            return $owned;
        }
        // staff: via storeStaff (StoreStaff has store_id + user_id)
        $staff = StoreStaff::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('store')
            ->first();
        if ($staff) {
            return $staff->store;
        }
        return null;
    }

    /**
     * Build admin menu based on user permissions.
     * Items are filtered to only those the user can actually access.
     */
    protected function buildAdminMenu(User $user): array
    {
        $all = [
            ['key' => 'overview', 'label' => 'Overview', 'url' => route('admin.dashboard', ['tab' => 'overview']), 'icon' => 'grid-fill', 'perm' => 'view_admin_dashboard'],
            ['key' => 'stores', 'label' => 'Stores', 'url' => route('admin.dashboard', ['tab' => 'stores']), 'icon' => 'shop', 'perm' => 'view_any_store'],
            ['key' => 'orders', 'label' => 'Orders', 'url' => route('admin.dashboard', ['tab' => 'orders']), 'icon' => 'box-seam', 'perm' => 'view_any_order'],
            ['key' => 'customers', 'label' => 'Customers', 'url' => route('admin.dashboard', ['tab' => 'customers']), 'icon' => 'people-fill', 'perm' => 'view_admin_dashboard'],
            ['key' => 'couriers', 'label' => 'Couriers', 'url' => route('admin.dashboard', ['tab' => 'couriers']), 'icon' => 'truck', 'perm' => 'view_any_courier'],
            ['key' => 'payments', 'label' => 'Payments', 'url' => route('admin.dashboard', ['tab' => 'payments']), 'icon' => 'cash-stack', 'perm' => 'view_any_payment'],
            ['key' => 'refunds', 'label' => 'Refunds', 'url' => route('admin.dashboard', ['tab' => 'refunds']), 'icon' => 'arrow-counterclockwise', 'perm' => 'view_any_payment'],
            ['key' => 'disputes', 'label' => 'Disputes', 'url' => route('admin.dashboard', ['tab' => 'disputes']), 'icon' => 'exclamation-triangle-fill', 'perm' => 'view_any_dispute'],
            ['key' => 'audit', 'label' => 'Audit Log', 'url' => route('admin.dashboard', ['tab' => 'audit']), 'icon' => 'shield-check', 'perm' => 'view_admin_dashboard'],
            // Owner-only
            ['key' => 'commission', 'label' => 'Commission', 'url' => route('admin.dashboard', ['tab' => 'commission']), 'icon' => 'percent', 'perm' => 'manage_platform'],
            ['key' => 'ai', 'label' => 'AI Usage', 'url' => route('admin.dashboard', ['tab' => 'ai']), 'icon' => 'cpu-fill', 'perm' => 'manage_platform'],
        ];

        return array_values(array_filter($all, fn($item) => $user->hasPermissionTo($item['perm'])));
    }
}
