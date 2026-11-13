<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\CommissionSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\StoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminPortalController extends Controller
{
    public function __construct(protected ?StoreService $storeService = null)
    {
        $this->storeService = $storeService ?? app(StoreService::class);
    }

    /**
     * Get platform overview metrics for admin.
     */
    public function overview(Request $request): JsonResponse
    {
        $storeStats = Store::selectRaw("
            COUNT(*) as total_stores,
            COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending_stores,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_stores
        ")->first();

        return response()->json([
            'metrics' => [
                'total_users' => User::count(),
                'total_stores' => (int) ($storeStats->total_stores ?? 0),
                'pending_stores' => (int) ($storeStats->pending_stores ?? 0),
                'approved_stores' => (int) ($storeStats->approved_stores ?? 0),
                'total_orders' => Order::count(),
            ],
        ]);
    }

    /**
     * Get detailed dashboard metrics for platform super admin.
     */
    public function metrics(Request $request): JsonResponse
    {
        $period = $request->query('period', 'all_time');

        // Orders by Status (support both lower & UPPERCASE for frontend status tile lookups)
        $rawOrderCounts = Order::groupBy('status')->selectRaw('status, count(*) as count')->pluck('count', 'status')->all();
        $ordersByStatus = [];
        $totalOrders = 0;
        foreach ($rawOrderCounts as $status => $count) {
            $count = (int) $count;
            $totalOrders += $count;
            $ordersByStatus[$status] = $count;
            $ordersByStatus[strtoupper($status)] = $count;
            if ($status === 'payment_required') {
                $ordersByStatus['AWAITING_PAYMENT'] = $count;
            }
        }
        $completedOrders = $ordersByStatus['completed'] ?? 0;

        // Stores KPI in single query
        $storeStats = Store::selectRaw("
            COUNT(*) as total_stores,
            COUNT(CASE WHEN status = 'approved' AND is_active = 1 THEN 1 END) as active_stores,
            COUNT(CASE WHEN status IN ('submitted', 'resubmitted', 'under_review') THEN 1 END) as pending_stores
        ")->first();

        $totalStores = (int) ($storeStats->total_stores ?? 0);
        $activeStores = (int) ($storeStats->active_stores ?? 0);
        $pendingStores = (int) ($storeStats->pending_stores ?? 0);

        // Users KPI in single query
        $userStats = User::selectRaw("
            COUNT(*) as total_users,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as suspended_users,
            COUNT(CASE WHEN role = ? THEN 1 END) as customer_count,
            COUNT(CASE WHEN role = ? THEN 1 END) as store_admin_count,
            COUNT(CASE WHEN role = ? THEN 1 END) as courier_count
        ", [User::ROLE_CUSTOMER, User::ROLE_STORE_ADMIN, User::ROLE_COURIER])->first();

        $totalUsers = (int) ($userStats->total_users ?? 0);
        $activeUsers = (int) ($userStats->active_users ?? 0);
        $suspendedUsers = (int) ($userStats->suspended_users ?? 0);

        // GMV & Revenue and Payments by Status in single query
        $paymentRows = DB::table('payments')
            ->selectRaw("
                status,
                count(*) as count,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount
            ")
            ->groupBy('status')
            ->get();

        $paymentsByStatus = [];
        $totalGmv = 0.0;
        foreach ($paymentRows as $pRow) {
            $paymentsByStatus[$pRow->status] = (int) $pRow->count;
            if ($pRow->status === 'paid') {
                $totalGmv = (float) $pRow->paid_amount;
            }
        }

        if ($totalGmv === 0.0) {
            $totalGmv = (float) Order::where('status', 'completed')->sum('total_amount');
        }
        $totalRevenue = round($totalGmv * 0.10, 2);

        // Users by Role
        $usersByRole = [
            'customer' => (int) ($userStats->customer_count ?? 0),
            'store_admin' => (int) ($userStats->store_admin_count ?? 0),
            'courier' => (int) ($userStats->courier_count ?? 0),
        ];

        // 10 Recent Orders with customer, store, status, amount (optimized via single join)
        $recentOrders = DB::table('orders')
            ->leftJoin('users', function ($join) {
                $join->on('orders.customer_id', '=', 'users.id')
                     ->whereNull('users.deleted_at');
            })
            ->leftJoin('stores', function ($join) {
                $join->on('orders.store_id', '=', 'stores.id')
                     ->whereNull('stores.deleted_at');
            })
            ->whereNull('orders.deleted_at')
            ->orderByDesc('orders.id')
            ->orderByDesc('orders.created_at')
            ->limit(10)
            ->select([
                'orders.id',
                'orders.order_number',
                'users.name as customer_name',
                'stores.name as store_name',
                'orders.status',
                'orders.final_price',
                'orders.total_amount',
                'orders.total_price',
                'orders.created_at',
            ])
            ->get()
            ->map(function ($order) {
                return [
                    'id' => (string) $order->id,
                    'order_number' => $order->order_number ?? "ORD-{$order->id}",
                    'customer_name' => $order->customer_name ?? 'Pelanggan',
                    'store_name' => $order->store_name ?? 'Toko Laundry',
                    'status' => $order->status,
                    'nominal' => (float) ($order->final_price ?? $order->total_amount ?? $order->total_price ?? 0),
                    'created_at' => $order->created_at ? Carbon::parse($order->created_at)->toIso8601String() : null,
                ];
            });

        // 10 Recent Audit Logs (optimized via single join)
        $recentLogs = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.actor_id', '=', 'users.id')
            ->orderByDesc('audit_logs.id')
            ->orderByDesc('audit_logs.created_at')
            ->limit(10)
            ->select([
                'audit_logs.id',
                'audit_logs.action',
                'audit_logs.resource_type',
                'audit_logs.resource_id',
                'users.name as actor_name',
                'audit_logs.created_at',
            ])
            ->get()
            ->map(function ($log) {
                return [
                    'id' => (string) $log->id,
                    'action' => $log->action,
                    'entity_type' => $log->resource_type ?? 'system',
                    'entity_id' => $log->resource_id ? (string) $log->resource_id : null,
                    'actor_name' => $log->actor_name ?? 'System',
                    'created_at' => $log->created_at ? Carbon::parse($log->created_at, 'Asia/Jakarta')->toIso8601String() : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'metrics' => [
                    'total_gmv' => $totalGmv,
                    'total_platform_revenue' => $totalRevenue,
                    'total_orders' => $totalOrders,
                    'completed_orders' => $completedOrders,
                    'active_stores' => $activeStores,
                    'pending_stores' => $pendingStores,
                    'total_stores' => $totalStores,
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'suspended_users' => $suspendedUsers,
                ],
                'orders_by_status' => $ordersByStatus,
                'users_by_role' => $usersByRole,
                'payments_by_status' => $paymentsByStatus,
                'recent_orders' => $recentOrders,
                'recent_logs' => $recentLogs,
            ],
        ]);
    }

    // =========================================================================
    // 1. ORDERS MONITORING
    // =========================================================================

    public function orders(Request $request): JsonResponse
    {
        $query = Order::with(['customer:id,name,email,phone', 'store:id,name', 'courier:id,name,phone']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$q}%"));
            });
        }

        $paginated = $query->latest('id')->paginate(15);

        $items = collect($paginated->items())->map(function ($order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    public function orderDetail(Request $request, $id): JsonResponse
    {
        $order = Order::with([
            'customer:id,name,email,phone',
            'store:id,name',
            'courier:id,name,phone',
            'items.service:id,name',
            'payments',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order, true),
        ]);
    }

    public function assignCourier(Request $request, $id): JsonResponse
    {
        $request->validate([
            'task_type' => 'nullable|string|in:pickup,delivery',
        ]);

        $order = Order::findOrFail($id);
        $taskType = $request->input('task_type', 'delivery');

        try {
            $task = app(\App\Services\Courier\CourierAssignmentService::class)->assignCourier($order, $taskType);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'actor_type' => 'user',
            'action' => 'ADMIN_ASSIGN_COURIER',
            'resource_type' => 'order',
            'resource_id' => (string) $order->id,
            'after_value' => json_encode([
                'task_id' => $task->id,
                'task_type' => $taskType,
                'courier_id' => $task->courier_id,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kurir ' . ($taskType === 'delivery' ? 'pengantaran' : 'penjemputan') . ' berhasil ditugaskan.',
            'data' => $task,
        ], 201);
    }

    protected function formatOrder(Order $order, bool $includeDetails = false): array
    {
        $finalAmount = (float) ($order->final_price ?? $order->total_amount ?? 0);
        $estimatedAmount = (float) ($order->estimated_price ?? $order->total_amount ?? 0);
        $commissionAmount = (float) ($order->commission_amount ?? ($finalAmount * 0.10));

        $data = [
            'id' => (string) $order->id,
            'order_number' => $order->order_number ?? "ORD-{$order->id}",
            'customer' => $order->customer ? [
                'id' => (string) $order->customer->id,
                'name' => $order->customer->name,
                'email' => $order->customer->display_email ?? $order->customer->email,
                'phone' => $order->customer->phone ?? '-',
            ] : null,
            'store' => $order->store ? [
                'id' => (string) $order->store->id,
                'name' => $order->store->name,
            ] : null,
            'courier' => $order->courier ? [
                'id' => (string) $order->courier->id,
                'name' => $order->courier->name ?? 'Kurir',
                'phone' => $order->courier->phone ?? '-',
            ] : null,
            'status' => $order->status,
            'payment_status' => $order->payment_status ?? ($order->payment?->status ?? 'unpaid'),
            'estimated_amount' => $estimatedAmount,
            'final_amount' => $finalAmount,
            'commission_amount' => $commissionAmount,
            'delivery_fee' => (float) ($order->delivery_fee ?? 0),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];

        if ($includeDetails) {
            $data['items'] = $order->items->map(function ($it) {
                return [
                    'id' => (string) $it->id,
                    'service_name_snapshot' => $it->service_name_snapshot ?? ($it->service?->name ?? 'Layanan'),
                    'quantity_estimated' => (float) ($it->quantity ?? $it->quantity_estimated ?? 1),
                    'quantity_actual' => $it->quantity_actual !== null ? (float) $it->quantity_actual : null,
                    'unit_price_snapshot' => (float) ($it->unit_price ?? $it->unit_price_snapshot ?? 0),
                    'line_final_amount' => $it->line_total !== null ? (float) $it->line_total : null,
                ];
            });

            $data['payments'] = $order->payments->map(function ($pay) {
                return [
                    'id' => (string) $pay->id,
                    'payment_number' => $pay->payment_number ?? "PAY-{$pay->id}",
                    'method' => $pay->payment_method ?? 'qris',
                    'amount' => (float) $pay->amount,
                    'status' => $pay->status,
                    'created_at' => $pay->created_at?->toIso8601String(),
                ];
            });
        }

        return $data;
    }

    // =========================================================================
    // 2. PAYMENTS & TRANSACTIONS
    // =========================================================================

    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with(['order.customer', 'order.store']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('method')) {
            $query->where('payment_method', $request->input('method'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('payment_number', 'like', "%{$q}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$q}%"));
            });
        }

        $paginated = $query->latest('id')->paginate(15);

        $items = collect($paginated->items())->map(function ($p) {
            return $this->formatPayment($p);
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    public function paymentDetail(Request $request, $id): JsonResponse
    {
        $payment = Payment::with(['order.customer', 'order.store'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatPayment($payment),
        ]);
    }

    protected function formatPayment(Payment $p): array
    {
        return [
            'id' => (string) $p->id,
            'payment_number' => $p->payment_number ?? "PAY-{$p->id}",
            'order_id' => (string) $p->order_id,
            'order_number' => $p->order?->order_number ?? "ORD-{$p->order_id}",
            'customer_name' => $p->order?->customer?->name ?? 'Pelanggan',
            'store_name' => $p->order?->store?->name ?? 'Toko Laundry',
            'provider' => $p->provider ?? 'xendit',
            'method' => $p->payment_method ?? 'qris',
            'amount' => (float) $p->amount,
            'status' => $p->status,
            'provider_reference' => $p->xendit_invoice_id ?? $p->provider_reference ?? null,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    // =========================================================================
    // 3. STORES & APPROVALS
    // =========================================================================

    public function stores(Request $request): JsonResponse
    {
        $query = Store::with(['owner:id,name,email,phone']);

        if ($request->filled('approval_status')) {
            $status = $request->input('approval_status');
            if ($status === 'approved') {
                $query->where('status', 'approved');
            } elseif ($status === 'rejected') {
                $query->where('status', 'rejected');
            } else {
                $query->whereNotIn('status', ['approved', 'rejected']);
            }
        }

        if ($request->filled('operational_status')) {
            $opStatus = $request->input('operational_status');
            $query->where('is_active', $opStatus === 'active');
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhereHas('owner', fn ($oq) => $oq->where('name', 'like', "%{$q}%"));
            });
        }

        $paginated = $query->latest('id')->paginate(15);

        $items = collect($paginated->items())->map(function ($s) {
            return $this->formatStore($s);
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    public function storeDetail(Request $request, $id): JsonResponse
    {
        $store = Store::with(['owner:id,name,email,phone', 'documents', 'prices.service'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatStore($store, true),
        ]);
    }

    public function approveStore(Request $request, $id): JsonResponse
    {
        $store = Store::findOrFail($id);
        $user = $request->user();

        if (in_array($store->status, ['draft', 'submitted', 'resubmitted'])) {
            $this->storeService->startReview($store, $user);
        }

        if ($store->status === 'under_review' || $store->status === 'approved') {
            if ($store->status !== 'approved') {
                $this->storeService->approve($store, $user, $request->input('note', 'Disetujui oleh Super Administrator'));
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Toko '{$store->name}' berhasil disetujui.",
            'data' => $this->formatStore($store->fresh(['owner'])),
        ]);
    }

    public function rejectStore(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:3',
        ]);

        $store = Store::findOrFail($id);
        $user = $request->user();

        if (in_array($store->status, ['draft', 'submitted', 'resubmitted'])) {
            $this->storeService->startReview($store, $user);
        }

        $this->storeService->reject($store, $user, $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => "Toko '{$store->name}' berhasil ditolak.",
            'data' => $this->formatStore($store->fresh(['owner'])),
        ]);
    }

    protected function formatStore(Store $s, bool $includeDetails = false): array
    {
        $approvalStatus = match ($s->status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'pending',
        };

        $data = [
            'id' => (string) $s->id,
            'name' => $s->name,
            'description' => $s->description,
            'address_text' => $s->address ?? '-',
            'latitude' => $s->latitude !== null ? (float) $s->latitude : null,
            'longitude' => $s->longitude !== null ? (float) $s->longitude : null,
            'service_radius_km' => (float) ($s->service_radius_km ?? 5),
            'opening_hours' => $s->operational_hours,
            'approval_status' => $approvalStatus,
            'operational_status' => $s->is_active ? 'active' : 'inactive',
            'rejection_reason' => $s->rejection_reason,
            'approved_at' => $s->approved_at?->toIso8601String(),
            'owner' => $s->owner ? [
                'id' => (string) $s->owner->id,
                'name' => $s->owner->name,
                'email' => $s->owner->display_email ?? $s->owner->email,
                'phone' => $s->owner->phone ?? '-',
            ] : null,
            'created_at' => $s->created_at?->toIso8601String(),
        ];

        if ($includeDetails) {
            $data['documents'] = $s->documents->map(function ($d) {
                return [
                    'id' => (string) $d->id,
                    'document_type' => $d->document_type,
                    'file_url' => $d->file_path ? asset("storage/{$d->file_path}") : '',
                    'status' => $d->status ?? 'submitted',
                    'created_at' => $d->created_at?->toIso8601String(),
                ];
            });

            $data['services'] = $s->prices->map(function ($p) {
                return [
                    'id' => (string) $p->id,
                    'name' => $p->service?->name ?? 'Layanan',
                    'category' => $p->service?->description,
                    'pricing_unit' => $p->unit ?? 'kg',
                    'price_per_unit' => (float) $p->price,
                    'is_active' => (bool) $p->is_active,
                ];
            });
        }

        return $data;
    }

    // =========================================================================
    // 4. USERS MANAGEMENT
    // =========================================================================

    public function users(Request $request): JsonResponse
    {
        $query = User::with('roles:id,name');

        if ($request->filled('role')) {
            $role = $request->input('role');
            $query->where(function ($w) use ($role) {
                $w->where('role', $role)
                    ->orWhereHas('roles', fn ($rq) => $rq->where('name', $role));
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->where('is_active', $status === 'active');
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $paginated = $query->latest('id')->paginate(15);

        $items = collect($paginated->items())->map(function ($u) {
            return $this->formatUser($u);
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    public function userDetail(Request $request, $id): JsonResponse
    {
        $user = User::with('roles:id,name')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatUser($user),
        ]);
    }

    public function updateUserStatus(Request $request, $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || (!$actor->hasPermissionTo('manage_users') && !$actor->hasRole('platform_owner') && !$actor->hasRole('super_admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Hak akses tidak memadai untuk mengubah status pengguna.',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:active,suspended,banned',
            'reason' => 'nullable|string',
        ]);

        $user = User::findOrFail($id);

        if (($user->hasRole('platform_owner') || $user->hasRole('super_admin')) && !$actor->hasRole('platform_owner')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Hanya Platform Owner yang dapat mengubah status akun administrator.',
            ], 403);
        }

        $status = $request->input('status');
        $user->is_active = ($status === 'active');
        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                    \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            } catch (\Throwable $e) {
                // Session driver may be array/file/redis
            }
        }

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role,
            'action' => 'ADMIN_USER_STATUS_UPDATE',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['status' => $status, 'reason' => $request->input('reason')]),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Status pengguna '{$user->name}' berhasil diperbarui.",
            'data' => $this->formatUser($user->fresh(['roles'])),
        ]);
    }

    protected function formatUser(User $u): array
    {
        $roles = $u->roles->pluck('name')->all();
        if (empty($roles)) {
            $roles = [$u->role];
        }

        return [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->display_email ?? $u->email,
            'phone' => $u->phone ?? '-',
            'status' => $u->is_active ? 'active' : 'suspended',
            'status_reason' => null,
            'roles' => $roles,
            'verified_at' => ($u->email_verified_at ?? $u->phone_verified_at)?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }

    // =========================================================================
    // 5. CATEGORIES (MASTER LAYANAN)
    // =========================================================================

    public function categories(Request $request): JsonResponse
    {
        $query = Service::query()->withCount('storePrices');

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->where('is_active', $status === 'active');
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where('name', 'like', "%{$q}%");
        }

        $categories = $query->orderBy('name')->get()->map(function ($s) {
            return [
                'id' => (string) $s->id,
                'name' => $s->name,
                'slug' => Str::slug($s->name),
                'status' => $s->is_active ? 'active' : 'inactive',
                'services_count' => (int) $s->store_prices_count,
                'created_at' => $s->created_at?->toIso8601String(),
                'updated_at' => $s->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:services,name',
            'status' => 'nullable|in:active,inactive',
        ]);

        $service = Service::create([
            'name' => $request->input('name'),
            'description' => $request->input('description', ''),
            'is_active' => $request->input('status', 'active') === 'active',
        ]);

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role,
            'action' => 'ADMIN_CATEGORY_CREATE',
            'resource_type' => 'service',
            'resource_id' => $service->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['name' => $service->name, 'is_active' => $service->is_active]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori layanan berhasil dibuat.',
            'data' => [
                'id' => (string) $service->id,
                'name' => $service->name,
                'slug' => Str::slug($service->name),
                'status' => $service->is_active ? 'active' : 'inactive',
                'services_count' => 0,
                'created_at' => $service->created_at?->toIso8601String(),
                'updated_at' => $service->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function updateCategory(Request $request, $id): JsonResponse
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:services,name,' . $service->id,
            'status' => 'nullable|in:active,inactive',
        ]);

        $oldValues = ['name' => $service->name, 'is_active' => $service->is_active];

        $service->name = $request->input('name');
        if ($request->has('status')) {
            $service->is_active = $request->input('status') === 'active';
        }
        $service->save();

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role,
            'action' => 'ADMIN_CATEGORY_UPDATE',
            'resource_type' => 'service',
            'resource_id' => $service->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'before_value' => json_encode($oldValues),
            'after_value' => json_encode(['name' => $service->name, 'is_active' => $service->is_active]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori layanan berhasil diperbarui.',
            'data' => [
                'id' => (string) $service->id,
                'name' => $service->name,
                'slug' => Str::slug($service->name),
                'status' => $service->is_active ? 'active' : 'inactive',
                'services_count' => $service->storePrices()->count(),
                'created_at' => $service->created_at?->toIso8601String(),
                'updated_at' => $service->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function deleteCategory(Request $request, $id): JsonResponse
    {
        $service = Service::findOrFail($id);

        $isUsedInPrices = $service->storePrices()->exists();
        $isUsedInOrders = $service->orderItems()->exists();
        $isUsed = $isUsedInPrices || $isUsedInOrders;

        if ($isUsed) {
            if ($request->boolean('deactivate')) {
                $service->is_active = false;
                $service->save();

                AuditLog::create([
                    'actor_id' => $request->user()?->id,
                    'actor_role' => $request->user()?->role,
                    'action' => 'ADMIN_CATEGORY_DEACTIVATE',
                    'resource_type' => 'service',
                    'resource_id' => (int) $id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'after_value' => json_encode(['is_active' => false, 'reason' => 'category_in_use']),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Kategori masih dipakai, status telah diubah menjadi nonaktif.',
                    'data' => [
                        'id' => (string) $id,
                        'is_active' => false,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Kategori masih dipakai',
                'errors' => [
                    'category' => ['Kategori masih dipakai'],
                ],
            ], 422);
        }

        $oldValues = ['name' => $service->name, 'is_active' => $service->is_active];
        $service->delete();

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role,
            'action' => 'ADMIN_CATEGORY_DELETE',
            'resource_type' => 'service',
            'resource_id' => (int) $id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'before_value' => json_encode($oldValues),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori layanan berhasil dihapus.',
            'data' => [
                'id' => (string) $id,
            ],
        ]);
    }

    // =========================================================================
    // 6. PLATFORM COMMISSION SETTING
    // =========================================================================

    public function getCommission(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermissionTo('view_commission') && !$user->hasRole('platform_owner') && !$user->hasRole('super_admin'))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Akses hanya untuk Platform Owner.'], 403);
        }
        $rate = (float) Setting::get('platform_commission_rate', 10);
        $updatedAt = Setting::where('key', 'platform_commission_rate')->value('updated_at');

        return response()->json([
            'success' => true,
            'data' => [
                'key' => 'platform_commission_rate',
                'amount' => $rate,
                'description' => 'Persentase komisi platform marketplace default',
                'updated_at' => $updatedAt ? $updatedAt->toIso8601String() : now()->toIso8601String(),
            ],
        ]);
    }

        protected function isPlatformOwner(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('platform_owner') || $user->hasPermissionTo('manage_platform')) {
            return true;
        }

        if ($user->hasRole('platform_admin')) {
            return false;
        }

        return $user->role === \App\Models\User::ROLE_SUPER_ADMIN;
    }
    public function updateCommission(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isPlatformOwner($user)) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Akses hanya untuk Platform Owner.'], 403);
        }
        $request->validate([
            'amount' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        $oldRate = (float) Setting::get('platform_commission_rate', 10);
        $rate = (float) $request->input('amount');
        Setting::set('platform_commission_rate', $rate, 'number', 'platform', $request->input('description'));

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role,
            'action' => 'ADMIN_COMMISSION_UPDATE',
            'resource_type' => 'setting',
            'resource_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'before_value' => json_encode(['platform_commission_rate' => $oldRate]),
            'after_value' => json_encode(['platform_commission_rate' => $rate, 'description' => $request->input('description')]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan komisi platform berhasil diperbarui.',
            'data' => [
                'key' => 'platform_commission_rate',
                'amount' => $rate,
                'description' => $request->input('description', 'Persentase komisi platform marketplace default'),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    // =========================================================================
    // 7. AUDIT LOGS
    // =========================================================================

    public function auditLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermissionTo('view_audit_logs') && !$user->hasRole('platform_owner') && !$user->hasRole('super_admin'))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Akses audit log dibatasi.'], 403);
        }
        $query = AuditLog::with('actor:id,name,email');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('entity_type')) {
            $query->where('resource_type', $request->input('entity_type'));
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->input('actor_id'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('action', 'like', "%{$q}%")
                    ->orWhere('resource_type', 'like', "%{$q}%")
                    ->orWhereHas('actor', fn ($aq) => $aq->where('name', 'like', "%{$q}%"));
            });
        }

        $paginated = $query->latest('id')->paginate(20);

        $items = collect($paginated->items())->map(function ($log) {
            return $this->formatAuditLog($log);
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    public function auditLogDetail(Request $request, $id): JsonResponse
    {
        $log = AuditLog::with('actor:id,name,email')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatAuditLog($log),
        ]);
    }

    protected function formatAuditLog(AuditLog $log): array
    {
        $metadata = null;
        if (!empty($log->after_value)) {
            $metadata = is_string($log->after_value) ? json_decode($log->after_value, true) : $log->after_value;
        }

        return [
            'id' => (string) $log->id,
            'actor' => $log->actor ? [
                'id' => (string) $log->actor->id,
                'name' => $log->actor->name,
                'email' => $log->actor->display_email ?? $log->actor->email,
            ] : null,
            'action' => $log->action,
            'entity_type' => $log->resource_type ?? 'system',
            'entity_id' => $log->resource_id ? (string) $log->resource_id : null,
            'metadata' => $metadata,
            'ip_hash' => $log->ip_address ? substr(md5($log->ip_address), 0, 10) : null,
            'created_at' => $this->formatAuditTimestamp($log),
        ];
    }

    protected function formatAuditTimestamp(AuditLog $log): ?string
    {
        $raw = $log->getRawOriginal('created_at');
        if (!$raw) {
            return $log->created_at?->toIso8601String();
        }

        // Kolom created_at pada audit_logs menggunakan MySQL DEFAULT CURRENT_TIMESTAMP (waktu sistem WIB).
        // Kita parse dengan timezone Asia/Jakarta (+07:00) agar tepat dan tidak bergeser +7 jam di browser.
        return Carbon::parse($raw, 'Asia/Jakarta')->toIso8601String();
    }
// =========================================================================
    // 8. AI ASSISTANT CONFIGURATION & MODEL SWITCHER
    // =========================================================================

    /**
     * GET /api/v1/admin/ai-settings
     */
    public function getAiSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermissionTo('view_ai_usage') && !$user->hasPermissionTo('manage_platform') && !$user->hasRole('platform_owner') && !$user->hasRole('super_admin'))) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Akses pengaturan AI dibatasi.'], 403);
        }
        $aiService = app(AiAssistantService::class);
        $activeModel = $aiService->getActiveModel();
        $temperature = $aiService->getTemperature();
        $maxToolRounds = $aiService->getMaxToolRounds();

        $setting = Setting::with('updatedByUser:id,name,email')->where('key', 'AI_MODEL')->first();
        $apiKey = config('ai.api_key', config('ai.openai_api_key'));

        $presets = [
            // --- Kategori Gratis (Free Tier OpenRouter - Saldo $0) ---
            [
                'id' => 'google/gemini-2.0-flash-exp:free',
                'name' => 'Google Gemini 2.0 Flash (Free)',
                'provider' => 'Google',
                'category' => 'free',
                'badge' => 'Rekomendasi Gratis',
                'description' => 'Model multimodal super cepat & cerdas dari Google tanpa biaya token.',
                'cost_estimate' => 'Rp 0 / Free Tier',
                'is_free' => true,
            ],
            [
                'id' => 'meta-llama/llama-3.3-70b-instruct:free',
                'name' => 'Meta Llama 3.3 70B (Free)',
                'provider' => 'Meta',
                'category' => 'free',
                'badge' => 'Open Source Gratis',
                'description' => 'Model 70B parameter open-source kelas atas dengan penalaran matang.',
                'cost_estimate' => 'Rp 0 / Free Tier',
                'is_free' => true,
            ],
            [
                'id' => 'deepseek/deepseek-r1:free',
                'name' => 'DeepSeek R1 (Free Reasoning)',
                'provider' => 'DeepSeek',
                'category' => 'free',
                'badge' => 'Penalaran Gratis',
                'description' => 'Model reasoning open-weights dengan penalaran langkah demi langkah.',
                'cost_estimate' => 'Rp 0 / Free Tier',
                'is_free' => true,
            ],
            [
                'id' => 'liquid/lfm-2.5-2.6b:free',
                'name' => 'Liquid LFM 2.5 2.6B (Free)',
                'provider' => 'Liquid',
                'category' => 'free',
                'badge' => 'Gratis Ringan',
                'description' => 'Model arsitektur LFM sangat ringan, ideal untuk pengujian dev/staging.',
                'cost_estimate' => 'Rp 0 / Free Tier',
                'is_free' => true,
            ],

            // --- Kategori Berbayar (Commercial Tier - Butuh Saldo OpenRouter) ---
            [
                'id' => 'google/gemini-2.5-flash',
                'name' => 'Google Gemini 2.5 Flash',
                'provider' => 'Google',
                'category' => 'paid',
                'badge' => 'Rekomendasi Utama',
                'description' => 'Sangat cepat, akurasi penalaran & pemanggilan tools optimal dengan latensi rendah.',
                'cost_estimate' => '~$0.075 / 1M token (Sangat Murah)',
                'is_free' => false,
            ],
            [
                'id' => 'openai/gpt-4o-mini',
                'name' => 'OpenAI GPT-4o Mini',
                'provider' => 'OpenAI',
                'category' => 'paid',
                'badge' => 'Standar Industri',
                'description' => 'Model hemat biaya OpenAI dengan kepatuhan format JSON dan tool-calling handal.',
                'cost_estimate' => '~$0.15 / 1M token (Hemat)',
                'is_free' => false,
            ],
            [
                'id' => 'deepseek/deepseek-chat',
                'name' => 'DeepSeek V3 (Chat)',
                'provider' => 'DeepSeek',
                'category' => 'paid',
                'badge' => 'Ekonomis & Handal',
                'description' => 'Model serbaguna performa tinggi dengan rasio efisiensi biaya luar biasa.',
                'cost_estimate' => '~$0.14 / 1M token (Murah)',
                'is_free' => false,
            ],
            [
                'id' => 'anthropic/claude-3.5-haiku',
                'name' => 'Anthropic Claude 3.5 Haiku',
                'provider' => 'Anthropic',
                'category' => 'paid',
                'badge' => 'Cepat & Cerdas',
                'description' => 'Model generasi kilat Anthropic dengan pemahaman konteks & keandalan tinggi.',
                'cost_estimate' => '~$0.80 / 1M token (Premium)',
                'is_free' => false,
            ],
        ];

        $catalog = $this->fetchOpenRouterCatalog();
        $presets = array_map(function (array $preset) use ($catalog): array {
            $preset['available_on_openrouter'] = $catalog !== [] ? isset($catalog[$preset['id']]) : null;
            return $preset;
        }, $presets);

        $presetIds = array_flip(array_column($presets, 'id'));
        $liveFreeModels = array_values(array_filter(
            $catalog,
            fn (array $m): bool => !empty($m['is_free']) && !isset($presetIds[$m['id']])
        ));
        usort($liveFreeModels, function (array $a, array $b): int {
            return (($b['supports_tools'] ?? false) <=> ($a['supports_tools'] ?? false))
                ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        $recentUsage = AiUsageLog::latest('id')
            ->limit(10)
            ->get(['id', 'model', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'status', 'flags', 'created_at'])
            ->map(fn (AiUsageLog $log): array => [
                'id' => (string) $log->id,
                'model' => $log->model,
                'resolved_model' => is_array($log->flags) ? ($log->flags['resolved_model'] ?? null) : null,
                'status' => $log->status,
                'prompt_tokens' => (int) $log->prompt_tokens,
                'completion_tokens' => (int) $log->completion_tokens,
                'total_tokens' => (int) $log->total_tokens,
                'error' => is_array($log->flags) ? ($log->flags['provider_error'] ?? null) : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $isCustom = !in_array($activeModel, array_column($presets, 'id'), true);

        return response()->json([
            'success' => true,
            'data' => [
                'active_model' => $activeModel,
                'temperature' => $temperature,
                'max_tool_rounds' => $maxToolRounds,
                'is_custom_model' => $isCustom,
                'presets' => $presets,
                'live_free_models' => $liveFreeModels,
                'recent_usage' => $recentUsage,
                'provider' => 'OpenRouter (OpenAI-compatible)',
                'api_key_configured' => !empty($apiKey),
                'updated_at' => $setting?->updated_at?->toIso8601String(),
                'updated_by' => $setting?->updatedByUser ? [
                    'id' => (string) $setting->updatedByUser->id,
                    'name' => $setting->updatedByUser->name,
                    'email' => $setting->updatedByUser->email,
                ] : null,
            ],
        ]);
    }

    /**
     * Fetch OpenRouter public model catalog (cached 1h; tests read cache only to stay offline-safe).
     *
     * @return array<string, array{id: string, name: string, is_free: bool, supports_tools: bool, context_length: int}>
     */
    protected function fetchOpenRouterCatalog(): array
    {
        $cacheKey = 'openrouter:models_catalog_v1';
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (app()->runningUnitTests()) {
            return [];
        }

        try {
            $response = Http::timeout(4)->acceptJson()->get('https://openrouter.ai/api/v1/models');
            if (!$response->successful()) {
                return [];
            }

            $map = [];
            foreach ((array) $response->json('data', []) as $m) {
                $id = (string) ($m['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $prompt = (float) ($m['pricing']['prompt'] ?? 1);
                $completion = (float) ($m['pricing']['completion'] ?? 1);
                $map[$id] = [
                    'id' => $id,
                    'name' => (string) ($m['name'] ?? $id),
                    'is_free' => str_ends_with($id, ':free') || ($prompt === 0.0 && $completion === 0.0 && $id !== 'openrouter/auto'),
                    'supports_tools' => in_array('tools', (array) ($m['supported_parameters'] ?? []), true),
                    'context_length' => (int) ($m['context_length'] ?? 0),
                ];
            }

            if ($map !== []) {
                Cache::put($cacheKey, $map, 3600);
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * PUT /api/v1/admin/ai-settings
     */
    public function updateAiSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isPlatformOwner($user)) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Hanya Platform Owner yang dapat mengubah pengaturan AI.'], 403);
        }
        $request->validate([
            'model' => 'required|string|min:3|max:120',
            'temperature' => 'nullable|numeric|min:0|max:1',
            'max_tool_rounds' => 'nullable|integer|min:1|max:10',
        ]);

        $aiService = app(AiAssistantService::class);
        $oldModel = $aiService->getActiveModel();
        $oldTemp = $aiService->getTemperature();
        $oldRounds = $aiService->getMaxToolRounds();

        $newModel = trim($request->input('model'));
        $newTemp = $request->has('temperature') ? (float) $request->input('temperature') : $oldTemp;
        $newRounds = $request->has('max_tool_rounds') ? (int) $request->input('max_tool_rounds') : $oldRounds;

        $userId = $request->user()?->id;

        Setting::set('AI_MODEL', $newModel, 'string', 'ai', 'Model LLM aktif chatbot LaundryKu', $userId);
        if ($request->has('temperature')) {
            Setting::set('AI_TEMPERATURE', $newTemp, 'float', 'ai', 'Temperatur kreativitas model LLM', $userId);
        }
        if ($request->has('max_tool_rounds')) {
            Setting::set('AI_MAX_TOOL_ROUNDS', $newRounds, 'integer', 'ai', 'Batas maksimal putaran tool calling AI', $userId);
        }

        $settingRecord = Setting::where('key', 'AI_MODEL')->first();

        AuditLog::create([
            'actor_id' => $userId,
            'actor_role' => $request->user()?->role,
            'action' => 'UPDATE_AI_MODEL',
            'resource_type' => 'setting',
            'resource_id' => $settingRecord?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'before_value' => json_encode([
                'model' => $oldModel,
                'temperature' => $oldTemp,
                'max_tool_rounds' => $oldRounds,
            ], JSON_UNESCAPED_SLASHES),
            'after_value' => json_encode([
                'model' => $newModel,
                'temperature' => $newTemp,
                'max_tool_rounds' => $newRounds,
            ], JSON_UNESCAPED_SLASHES),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Model AI berhasil diubah menjadi {$newModel}.",
            'data' => [
                'active_model' => $newModel,
                'temperature' => $newTemp,
                'max_tool_rounds' => $newRounds,
                'updated_at' => now()->toIso8601String(),
                'updated_by' => $request->user() ? [
                    'id' => (string) $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/ai-settings/test
     */
    public function testAiModel(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isPlatformOwner($user)) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Hanya Platform Owner yang dapat menguji model AI.'], 403);
        }
        $request->validate([
            'model' => 'required|string|min:3|max:120',
        ]);

        $model = trim($request->input('model'));
        $aiService = app(AiAssistantService::class);
        $result = $aiService->testModelConnection($model);

        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'Uji koneksi selesai.',
            'data' => $result,
        ], $status);
    }
}