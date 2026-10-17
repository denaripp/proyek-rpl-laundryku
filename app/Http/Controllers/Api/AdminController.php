<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\CommissionSetting;
use App\Models\Dispute;
use App\Models\FailedLoginAttempt;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 16 — Platform admin dashboard metrics.
 * Tahap 18 — extended with per-menu endpoints + owner-only areas.
 */
class AdminController extends Controller
{
    public function __construct(protected DashboardMetricsService $metrics) {}

    protected function ensureAdmin(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('view_admin_dashboard')) {
            abort(response()->json(['error' => 'Forbidden.'], 403));
        }
    }

    protected function ensureOwner(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('manage_platform')) {
            abort(response()->json(['error' => 'Owner only.'], 403));
        }
    }

    protected function ensurePerm(Request $request, string $perm)
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo($perm)) {
            abort(response()->json(['error' => "Forbidden: missing {$perm}."], 403));
        }
    }

    /**
     * GET /api/admin/dashboard
     */
    public function dashboard(Request $request)
    {
        $this->ensureAdmin($request);

        $m = fn($k) => $this->metrics->get('platform', $k);
        $isOwner = $request->user()->hasPermissionTo('manage_platform');

        $stats = [
            'users_total' => User::count(),
            'users_active' => User::where('is_active', true)->count(),
            'stores_total' => Store::count(),
            'stores_approved' => Store::where('status', 'approved')->count(),
            'stores_pending' => Store::whereIn('status', ['submitted', 'resubmitted', 'under_review'])->count(),
            'orders_total' => Order::count(),
            'orders_today' => Order::whereDate('created_at', today())->count(),
            'orders_in_progress' => Order::whereNotIn('status', ['completed', 'cancelled', 'delivered'])->count(),
            'revenue_today' => (float) Payment::where('status', 'paid')->whereDate('paid_at', today())->sum('amount'),
            'revenue_month' => (float) Payment::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
            'refunds_pending' => Refund::whereIn('status', ['requested', 'processing'])->count(),
            'gmv_today' => $m('gmv_today'),
            'gmv_month' => $m('gmv_month'),
            'completion_rate' => $m('completion_rate'),
        ];

        return response()->json([
            'stats' => $stats,
            'menu' => $this->buildAdminMenu($request->user()),
            'is_owner' => $isOwner,
        ]);
    }

    /**
     * GET /api/admin/users
     */
    public function users(Request $request)
    {
        $this->ensureAdmin($request);

        $query = User::with('roles:id,name');

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->input('role')));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%");
            });
        }

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * GET /api/admin/stores
     */
    public function stores(Request $request)
    {
        $this->ensureAdmin($request);

        $query = Store::with('owner:id,name,email');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * GET /api/admin/payments — payment aggregate + recent list.
     * Perm: view_any_payment.
     */
    public function payments(Request $request)
    {
        $this->ensurePerm($request, 'view_any_payment');

        $recent = Payment::with('order:id,order_number,store_id,total_amount')
            ->latest('id')
            ->limit(50)
            ->get();

        $today = (float) Payment::where('status', 'paid')->whereDate('paid_at', today())->sum('amount');
        $month = (float) Payment::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount');
        $cod = (float) Payment::where('status', 'paid')->where('payment_method', 'cod')
            ->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount');
        $online = $month - $cod;

        return response()->json([
            'stats' => [
                'today' => $today,
                'month' => $month,
                'month_cod' => $cod,
                'month_online' => $online,
            ],
            'recent' => $recent,
        ]);
    }

    /**
     * GET /api/admin/refunds — all refunds + pending breakdown.
     * Perm: view_any_refund (sama dengan payment area).
     */
    public function refunds(Request $request)
    {
        $this->ensurePerm($request, 'view_any_refund');

        $query = Refund::with('order:id,order_number,store_id', 'payment:id,amount,payment_method');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'pending_count' => Refund::whereIn('status', ['requested', 'processing'])->count(),
            'pending_amount' => (float) Refund::whereIn('status', ['requested', 'processing'])->sum('amount'),
            'recent' => $query->latest()->limit(50)->get(),
        ]);
    }

    /**
     * GET /api/admin/disputes — disputes list.
     * Perm: view_any_dispute.
     */
    public function disputes(Request $request)
    {
        $this->ensurePerm($request, 'view_any_dispute');

        $query = Dispute::with('order:id,order_number', 'openedBy:id,name');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'open_count' => Dispute::whereIn('status', Dispute::OPEN_STATUSES)->count(),
            'recent' => $query->latest()->limit(50)->get(),
        ]);
    }

    /**
     * GET /api/admin/notifications — global notification feed.
     * Perm: view_admin_dashboard.
     */
    public function notifications(Request $request)
    {
        $this->ensureAdmin($request);

        $recent = Notification::with('user:id,name')
            ->latest('id')
            ->limit(50)
            ->get();

        $unreadTotal = (int) Notification::whereNull('read_at')->count();

        return response()->json([
            'unread_total' => $unreadTotal,
            'recent' => $recent,
        ]);
    }

    /**
     * GET /api/admin/audit — audit log feed.
     * Perm: view_admin_dashboard.
     */
    public function audit(Request $request)
    {
        $this->ensureAdmin($request);

        $query = AuditLog::with('actor:id,name,email');
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }

        return response()->json([
            'count_24h' => AuditLog::where('created_at', '>=', now()->subDay())->count(),
            'recent' => $query->latest('id')->limit(50)->get(),
        ]);
    }

    // ========== OWNER-ONLY ==========

    /**
     * GET /api/admin/commission — commission rates + GMV-based estimate.
     * Perm: view_commission.
     */
    public function commission(Request $request)
    {
        $this->ensurePerm($request, 'view_commission');

        $settings = CommissionSetting::where('is_active', true)->get();
        $paidThisMonth = (float) Payment::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)
            ->sum('amount');

        $totalEst = 0.0;
        foreach ($settings as $s) {
            $totalEst += $paidThisMonth * ((float) $s->commission_rate / 100.0);
        }

        return response()->json([
            'active_rates' => $settings,
            'gmr_paid_month' => $paidThisMonth,
            'estimated_commission_month' => round($totalEst, 2),
        ]);
    }

    /**
     * GET /api/admin/security — failed login + audit snapshot.
     * Perm: view_security.
     */
    public function security(Request $request)
    {
        $this->ensurePerm($request, 'view_security');

        return response()->json([
            'failed_logins_24h' => FailedLoginAttempt::where('attempted_at', '>=', now()->subDay())->count(),
            'failed_logins_7d' => FailedLoginAttempt::where('attempted_at', '>=', now()->subDays(7))->count(),
            'recent_failed_logins' => FailedLoginAttempt::latest('attempted_at')->limit(20)->get(),
            'audit_events_24h' => AuditLog::where('created_at', '>=', now()->subDay())->count(),
            'recent_audit_critical' => AuditLog::whereIn('action', [
                'login', 'logout', 'login_failed', 'permission_denied', 'store_suspended',
            ])->latest('id')->limit(20)->get(),
        ]);
    }

    /**
     * GET /api/admin/ai-usage — AI cost + token aggregate.
     * Perm: view_ai_usage.
     */
    public function aiUsage(Request $request)
    {
        $this->ensurePerm($request, 'view_ai_usage');

        $today = AiUsageLog::whereDate('created_at', today());
        $month = AiUsageLog::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);

        return response()->json([
            'calls_today' => $today->count(),
            'cost_today' => (float) $today->sum('estimated_cost_usd'),
            'tokens_today' => (int) $today->sum('total_tokens'),
            'calls_month' => $month->count(),
            'cost_month' => (float) $month->sum('estimated_cost_usd'),
            'tokens_month' => (int) $month->sum('total_tokens'),
            'abuse_blocked_24h' => (int) AiUsageLog::where('status', 'abuse_blocked')
                ->where('created_at', '>=', now()->subDay())->count(),
            'rate_limited_24h' => (int) AiUsageLog::where('status', 'rate_limited')
                ->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }

    /**
     * GET /api/admin/menu — admin menu based on permissions.
     */
    public function menu(Request $request)
    {
        $this->ensureAdmin($request);

        return response()->json([
            'menu' => $this->buildAdminMenu($request->user()),
            'is_owner' => $request->user()->hasPermissionTo('manage_platform'),
        ]);
    }

    /**
     * GET /api/admin/settings
     * List all system_configurations (filterable by category).
     * Perm: view_admin_dashboard (read), manage_settings (write).
     */
    public function settingsIndex(Request $request)
    {
        $this->ensureAdmin($request);

        $query = \App\Models\SystemConfiguration::query();
        if ($request->filled('category')) {
            // Heuristic: filter by key prefix.
            $cat = $request->input('category');
            $query->where('key', 'like', strtoupper($cat) . '%');
        }

        return response()->json([
            'settings' => $query->orderBy('key')->get(),
            'count' => $query->count(),
        ]);
    }

    /**
     * PATCH /api/admin/settings/{key}
     * Update a single system configuration. Logs to audit_logs.
     * Perm: manage_settings.
     */
    public function settingsUpdate(Request $request, string $key)
    {
        $this->ensurePerm($request, 'manage_settings');

        $config = \App\Models\SystemConfiguration::where('key', $key)->first();
        if (!$config) {
            return response()->json(['error' => "Unknown setting key: {$key}"], 404);
        }
        if (!$config->is_editable) {
            return response()->json(['error' => 'Setting is not editable.'], 403);
        }

        $request->validate([
            'value' => 'required|array',
        ]);

        $oldValue = $config->value;
        $config->value = $request->input('value');
        $config->save();

        // SECURITY (Tahap 21): log every settings change to audit_logs.
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'system_setting_updated',
            'resource_type' => 'system_configuration',
            'resource_id' => $config->id,
            'before_value' => ['key' => $key, 'value' => $oldValue],
            'after_value' => ['key' => $key, 'value' => $config->value],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json($config);
    }

    /**
     * GET /api/admin/monitoring — operational health snapshot.
     * Perm: view_monitoring.
     */
    public function monitoring(Request $request)
    {
        $this->ensurePerm($request, 'view_monitoring');

        // 1. Application errors (last 24h) — failed test errors logged.
        $recentErrors = \Illuminate\Support\Facades\DB::table('audit_logs')
            ->whereIn('action', ['login_failed', 'permission_denied', 'webhook_error', 'refund_failed'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        // 2. Queue health: jobs in last 24h grouped by status.
        $queue = [
            'failed_24h' => \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count(),
            'failed_total' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
        ];

        // 3. Webhook failure rate (Xendit webhooks — last 24h).
        // `processed=false && error is not null` counts as failed webhook
        // (XenditWebhook table doesn't have a `status` column; it has
        // `processed` + `error` fields).
        $webhook24h = \App\Models\XenditWebhook::where('created_at', '>=', now()->subDay())->count();
        $webhookFailed24h = \App\Models\XenditWebhook::where('created_at', '>=', now()->subDay())
            ->where('processed', false)
            ->whereNotNull('error')
            ->count();

        // 4. AI usage + cost (already tracked in aiUsage endpoint).
        $aiToday = \App\Models\AiUsageLog::whereDate('created_at', today())->count();
        $aiCostToday = (float) \App\Models\AiUsageLog::whereDate('created_at', today())->sum('estimated_cost_usd');

        // 5. DB perf: query counts from last 24h (audit_logs as proxy).
        $dbRows24h = (int) \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('created_at', '>=', now()->subDay())->count();

        return response()->json([
            'application_errors_24h' => $recentErrors,
            'queue' => $queue,
            'webhook_24h' => [
                'received' => $webhook24h,
                'failed' => $webhookFailed24h,
                'failure_rate_percent' => $webhook24h > 0
                    ? round(($webhookFailed24h / $webhook24h) * 100, 2)
                    : 0.0,
            ],
            'ai' => [
                'calls_today' => $aiToday,
                'cost_today_usd' => round($aiCostToday, 4),
            ],
            'database' => [
                'audit_rows_24h' => $dbRows24h,
            ],
            'snapshot_at' => now()->toISOString(),
        ]);
    }

    // ============== HELPERS ==============

    protected function buildAdminMenu(User $user): array
    {
        $all = [
            ['key' => 'stores',         'label' => 'Stores',         'perm' => 'view_any_store'],
            ['key' => 'orders',         'label' => 'Orders',         'perm' => 'view_any_order'],
            ['key' => 'customers',      'label' => 'Customers',      'perm' => 'view_admin_dashboard'],
            ['key' => 'couriers',       'label' => 'Couriers',       'perm' => 'view_any_courier'],
            ['key' => 'payments',       'label' => 'Payments',       'perm' => 'view_any_payment'],
            ['key' => 'cod',            'label' => 'COD',            'perm' => 'view_any_payment'],
            ['key' => 'refunds',        'label' => 'Refunds',        'perm' => 'view_any_refund'],
            ['key' => 'disputes',       'label' => 'Disputes',       'perm' => 'view_any_dispute'],
            ['key' => 'notifications',  'label' => 'Notifications',  'perm' => 'view_admin_dashboard'],
            ['key' => 'audit',          'label' => 'Audit Log',      'perm' => 'view_admin_dashboard'],
            // Owner-only
            ['key' => 'commission',     'label' => 'Commission',     'perm' => 'view_commission'],
            ['key' => 'security',       'label' => 'Security',       'perm' => 'view_security'],
            ['key' => 'ai_usage',       'label' => 'AI Usage',       'perm' => 'view_ai_usage'],
            ['key' => 'settings',       'label' => 'Settings',       'perm' => 'manage_settings'],
            ['key' => 'monitoring',     'label' => 'Monitoring',     'perm' => 'view_monitoring'],
        ];

        return array_values(array_filter($all, fn($i) => $user->hasPermissionTo($i['perm'])));
    }
}
