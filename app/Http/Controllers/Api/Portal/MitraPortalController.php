<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Role;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\StorePayoutAccount;
use App\Models\StoreStaff;
use App\Models\User;
use App\Notifications\NotificationTrigger;
use App\Http\Requests\Mitra\UpdateStoreProfileRequest;
use App\Services\Store\StoreDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MitraPortalController extends Controller
{
    /**
     * Resolve store for authenticated mitra (covers store owner AND active store staff).
     */
    protected function resolveStore(User $user, ?string $storeId = null): ?Store
    {
        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where(function ($q) use ($user) {
                    $q->where('owner_user_id', $user->id)
                      ->orWhereHas('staff', fn ($s) => $s->where('user_id', $user->id)->where('is_active', true));
                })
                ->first();
            if ($store) {
                return $store;
            }
        }

        // 1. Check if user is the store owner
        $owned = Store::where('owner_user_id', $user->id)->first();
        if ($owned) {
            return $owned;
        }

        // 2. Check if user is active staff of a store
        $staff = StoreStaff::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('store')
            ->first();

        return $staff?->store;
    }

    /**
     * Format timestamp with Asia/Jakarta timezone.
     */
    protected function formatTimestamp(?string $raw, $fallback = null): ?string
    {
        if ($raw) {
            return Carbon::parse($raw, 'Asia/Jakarta')->toIso8601String();
        }
        return $fallback ? Carbon::parse($fallback)->setTimezone('Asia/Jakarta')->toIso8601String() : null;
    }

    /**
     * Format a service price item.
     */
    protected function formatServicePrice(ServicePrice $sp): array
    {
        $rawCreated = $sp->getRawOriginal('created_at');
        $rawUpdated = $sp->getRawOriginal('updated_at');

        return [
            'id' => (string) $sp->id,
            'store_id' => (string) $sp->store_id,
            'category_id' => (string) $sp->service_id,
            'category_name' => $sp->service?->name ?? 'Layanan',
            'category_slug' => Str::slug($sp->service?->name ?? 'layanan'),
            'name' => $sp->service?->name ?? 'Layanan Laundry',
            'description' => $sp->service?->description,
            'pricing_unit' => $sp->unit ?? 'kg',
            'price_per_unit' => (float) $sp->price_per_unit,
            'minimum_charge' => (float) ($sp->minimum_charge ?? 0),
            'estimated_duration_hours' => (int) ($sp->estimated_duration_hours ?? 24),
            'is_active' => (bool) $sp->is_active,
            'created_at' => $this->formatTimestamp($rawCreated, $sp->created_at),
            'updated_at' => $this->formatTimestamp($rawUpdated, $sp->updated_at),
        ];
    }

    /**
     * GET /api/v1/mitra/store & /api/v1/mitra/profile
     */
    public function storeProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $isOwner = (int) $store->owner_user_id === (int) $user->id;

        $storeData = [
            'id' => (string) $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'description' => $store->description,
            'phone' => $store->phone,
            'address' => $store->address,
            'city' => $store->city,
            'province' => $store->province,
            'latitude' => $store->latitude !== null ? (float) $store->latitude : null,
            'longitude' => $store->longitude !== null ? (float) $store->longitude : null,
            'service_radius_km' => $store->service_radius_km !== null ? (float) $store->service_radius_km : null,
            'operational_hours' => $store->operational_hours,
            'status' => $store->status,
            'is_active' => (bool) $store->is_active,
            'is_approved' => $store->isApproved(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'store' => $storeData,
                'membership' => [
                    'is_owner' => $isOwner,
                    'is_staff' => ! $isOwner,
                ],
            ],
            // Backwards-compatible root keys
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'phone' => $store->phone,
                'address' => $store->address,
                'city' => $store->city,
                'province' => $store->province,
                'latitude' => $store->latitude !== null ? (float) $store->latitude : null,
                'longitude' => $store->longitude !== null ? (float) $store->longitude : null,
                'service_radius_km' => $store->service_radius_km !== null ? (float) $store->service_radius_km : null,
                'operational_hours' => $store->operational_hours,
                'status' => $store->status,
                'is_active' => $store->is_active,
                'is_approved' => $store->isApproved(),
            ],
            'membership' => [
                'is_owner' => $isOwner,
                'is_staff' => ! $isOwner,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/mitra/store — Update store profile (owner only).
     */
    public function updateStoreProfile(UpdateStoreProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        // Syarat: Hanya pemilik toko (owner_user_id = user login); staf atau pihak lain ditolak 403.
        if ((int) $store->owner_user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pemilik toko yang memiliki izin untuk mengubah profil gerai.',
            ], 403);
        }

        $validated = $request->validated();
        // Whitelist HANYA field yang diizinkan (slug, status, is_active, is_approved, owner_user_id dll TIDAK boleh diubah)
        $allowedFields = [
            'name',
            'phone',
            'address',
            'description',
            'city',
            'province',
            'latitude',
            'longitude',
            'service_radius_km',
            'operational_hours',
        ];
        $allowed = array_intersect_key($validated, array_flip($allowedFields));

        $store->fill($allowed);
        $store->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_PROFILE_UPDATE',
            'resource_type' => 'store',
            'resource_id' => $store->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode($allowed),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil gerai berhasil diperbarui.',
            'data' => [
                'store' => [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'description' => $store->description,
                    'phone' => $store->phone,
                    'address' => $store->address,
                    'city' => $store->city,
                    'province' => $store->province,
                    'latitude' => $store->latitude !== null ? (float) $store->latitude : null,
                    'longitude' => $store->longitude !== null ? (float) $store->longitude : null,
                    'service_radius_km' => $store->service_radius_km !== null ? (float) $store->service_radius_km : null,
                    'operational_hours' => $store->operational_hours,
                    'status' => $store->status,
                    'is_active' => (bool) $store->is_active,
                    'is_approved' => $store->isApproved(),
                ],
                'membership' => [
                    'is_owner' => true,
                    'is_staff' => false,
                ],
            ],
        ]);
    }

    /**
     * PUT /api/v1/mitra/password — Change password for store admin.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah.',
                'errors' => [
                    'current_password' => ['Password saat ini salah.'],
                ],
            ], 422);
        }

        // Simpan hash SEKALI (User model tidak memiliki cast 'hashed')
        $user->password = Hash::make($request->input('password'));
        $user->save();

        // Batalkan token Sanctum lain milik user, simpan token sesi saat ini agar tetap aktif
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        // Catat AuditLog tanpa mencatat password
        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'MITRA_PASSWORD_UPDATE',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['password_changed' => true]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    /**
     * GET /api/v1/mitra/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        if (! $store->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Toko Anda masih berstatus pending dan belum dapat mengakses daftar pesanan.',
            ], 403);
        }

        $orders = Order::where('store_id', $store->id)
            ->with(['customer:id,name,phone', 'items'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            // Backwards-compatible paginator fields
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
        ]);
    }

    /**
     * GET /api/v1/store/dashboard
     */
    public function dashboard(Request $request, ?StoreDashboardService $dashboardService = null): JsonResponse
    {
        $dashboardService = $dashboardService ?? app(StoreDashboardService::class);
        $user = $request->user();
        $store = $this->resolveStore($user, $request->query('store_id'));

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $data = $dashboardService->getDashboardData($store);

        return response()->json([
            'success' => true,
            'data' => [
                'store' => $data['store'],
                'metrics' => [
                    'new_orders' => $data['metrics']['new_orders'],
                    'processing_orders' => $data['metrics']['processing_orders'],
                    'ready_for_delivery' => $data['metrics']['ready_for_delivery'],
                    'completed_today' => $data['metrics']['completed_today'],
                    'today_revenue' => $data['metrics']['today_revenue'],
                ],
                'recent_orders' => $data['recent_orders'],
            ],
        ]);
    }

    /**
     * GET /api/v1/store/sidebar-counts
     */
    public function sidebarCounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user, $request->query('store_id'));

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $rejectedIds = AuditLog::where('action', 'STORE_COURIER_REJECT')
            ->where('resource_type', 'courier')
            ->where(function ($q) use ($user, $store) {
                $q->where('actor_id', $user->id)
                  ->orWhere('after_value', 'like', '%"store_id":' . $store->id . '%')
                  ->orWhere('after_value', 'like', '%"store_id":"' . $store->id . '"%');
            })
            ->pluck('resource_id')
            ->toArray();

        $pelamarQuery = Courier::where(function ($q) use ($store) {
            $q->where(function ($sub) use ($store) {
                $sub->where('store_id', $store->id)
                    ->where('is_verified', false);
            })->orWhere(function ($sub) {
                $sub->whereNull('store_id')
                    ->where('is_verified', true);
            });
        });
        if (! empty($rejectedIds)) {
            $pelamarQuery->whereNotIn('id', $rejectedIds);
        }
        $pelamarKurirPending = $pelamarQuery->count();

        $pesananBaru = Order::where('store_id', $store->id)
            ->whereIn('status', ['created', 'pending_pickup'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pelamar_kurir_pending' => $pelamarKurirPending,
                'pesanan_baru' => $pesananBaru,
                'unread_chat' => 0,
                'store_id' => (string) $store->id,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/store/operational-status
     */
    public function updateOperationalStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $newStatus = $request->input('operational_status');
        if (! $newStatus) {
            $store->is_active = ! $store->is_active;
        } else {
            $store->is_active = ($newStatus === 'active');
        }
        $store->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_OPERATIONAL_STATUS_UPDATE',
            'resource_type' => 'store',
            'resource_id' => $store->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['is_active' => $store->is_active]),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'operational_status' => $store->is_active ? 'active' : 'inactive',
            ],
        ]);
    }

    /**
     * GET /api/v1/store/services
     */
    public function services(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $query = ServicePrice::where('store_id', $store->id)->with('service');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('service', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('service_id', $request->input('category_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        } elseif ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->input('sort') === 'name_asc') {
            $query->join('services', 'services.id', '=', 'service_prices.service_id')
                ->orderBy('services.name', 'asc')
                ->select('service_prices.*');
        } elseif ($request->input('sort') === 'price_asc') {
            $query->orderBy('price_per_unit', 'asc');
        } elseif ($request->input('sort') === 'price_desc') {
            $query->orderBy('price_per_unit', 'desc');
        } else {
            $query->latest();
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 20)));
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(fn ($sp) => $this->formatServicePrice($sp))->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'total' => $paginated->total(),
                'active_count' => ServicePrice::where('store_id', $store->id)->where('is_active', true)->count(),
                'inactive_count' => ServicePrice::where('store_id', $store->id)->where('is_active', false)->count(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/store/services
     */
    public function createService(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $request->validate([
            'category_id' => 'required|exists:services,id',
            'pricing_unit' => 'nullable|string|in:kg,item',
            'price_per_unit' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $sp = ServicePrice::updateOrCreate(
            ['store_id' => $store->id, 'service_id' => $request->input('category_id')],
            [
                'price_per_unit' => $request->input('price_per_unit'),
                'unit' => $request->input('pricing_unit', 'kg'),
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_SERVICE_CREATE',
            'resource_type' => 'service_price',
            'resource_id' => $sp->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode($sp->toArray()),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatServicePrice($sp->load('service')),
        ], 201);
    }

    /**
     * PATCH /api/v1/store/services/{id}
     */
    public function updateService(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $sp = ServicePrice::where('store_id', $store->id)->findOrFail($id);

        if ($request->has('price_per_unit')) {
            $sp->price_per_unit = $request->input('price_per_unit');
        }
        if ($request->has('pricing_unit')) {
            $sp->unit = $request->input('pricing_unit');
        }
        if ($request->has('is_active')) {
            $sp->is_active = $request->boolean('is_active');
        }
        if ($request->has('category_id')) {
            $sp->service_id = $request->input('category_id');
        }
        $sp->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_SERVICE_UPDATE',
            'resource_type' => 'service_price',
            'resource_id' => $sp->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode($sp->toArray()),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatServicePrice($sp->load('service')),
        ]);
    }

    /**
     * DELETE /api/v1/store/services/{id}
     */
    public function deleteService(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $sp = ServicePrice::where('store_id', $store->id)->findOrFail($id);

        $isUsed = OrderItem::where('service_id', $sp->service_id)
            ->whereHas('order', fn ($q) => $q->where('store_id', $store->id))
            ->exists();

        if ($isUsed) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan masih dipakai',
            ], 422);
        }

        $sp->delete();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_SERVICE_DELETE',
            'resource_type' => 'service_price',
            'resource_id' => (int) $id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $id,
                'deleted' => true,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/store/services/{id}/status
     */
    public function toggleServiceStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $sp = ServicePrice::where('store_id', $store->id)->findOrFail($id);

        if ($request->has('is_active')) {
            $sp->is_active = $request->boolean('is_active');
        } else {
            $sp->is_active = ! $sp->is_active;
        }
        $sp->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_SERVICE_STATUS_UPDATE',
            'resource_type' => 'service_price',
            'resource_id' => $sp->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['is_active' => $sp->is_active]),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatServicePrice($sp->load('service')),
        ]);
    }

    /**
     * POST /api/v1/store/services/bulk-status
     */
    public function bulkServiceStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $ids = $request->input('ids', []);
        $isActive = $request->boolean('is_active');

        ServicePrice::where('store_id', $store->id)
            ->whereIn('id', $ids)
            ->update(['is_active' => $isActive]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_SERVICE_BULK_STATUS_UPDATE',
            'resource_type' => 'service_price',
            'resource_id' => 0,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['ids' => $ids, 'is_active' => $isActive]),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'updated_count' => count($ids),
                'is_active' => $isActive,
            ],
        ]);
    }

    /**
     * GET /api/v1/store/ledger
     */
    public function ledger(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $orders = Order::where('store_id', $store->id)
            ->whereIn('status', ['paid', 'processing', 'ready_for_delivery', 'delivery_assigned', 'out_for_delivery', 'completed'])
            ->with('customer:id,name')
            ->latest('created_at')
            ->get();

        $digitalPayments = 0;
        $codDeposits = 0;
        $courierWages = 0;
        $platformCommission = 0;
        $entries = [];

        foreach ($orders as $order) {
            $amount = (float) ($order->final_price ?? $order->total_amount ?? 0);
            $wage = (float) ($order->delivery_fee ?? 0);
            $comm = (float) ($order->commission_amount ?? 0);

            $rawCreated = $order->getRawOriginal('created_at');
            $createdAtIso = $this->formatTimestamp($rawCreated, $order->created_at);

            $isCod = strtolower($order->payment_method ?? '') === 'cod';

            if ($isCod) {
                $codDeposits += $amount;
                $entries[] = [
                    'id' => 'cod_' . $order->id,
                    'entry_type' => 'COD_DEPOSIT',
                    'direction' => 'credit',
                    'amount' => $amount,
                    'description' => 'Setoran tunai COD pesanan ' . $order->order_number,
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'created_by_name' => $order->customer?->name ?? 'Pelanggan',
                    'created_at' => $createdAtIso,
                ];
            } else {
                $digitalPayments += $amount;
                $entries[] = [
                    'id' => 'pay_' . $order->id,
                    'entry_type' => 'PAYMENT_RECEIPT',
                    'direction' => 'credit',
                    'amount' => $amount,
                    'description' => 'Pembayaran digital pesanan ' . $order->order_number,
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'created_by_name' => $order->customer?->name ?? 'Pelanggan',
                    'created_at' => $createdAtIso,
                ];
            }

            if ($wage > 0) {
                $courierWages += $wage;
                $entries[] = [
                    'id' => 'wage_' . $order->id,
                    'entry_type' => 'COURIER_WAGE',
                    'direction' => 'debit',
                    'amount' => $wage,
                    'description' => 'Upah kurir pesanan ' . $order->order_number,
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'created_by_name' => 'Sistem Logistik',
                    'created_at' => $createdAtIso,
                ];
            }

            if ($comm > 0) {
                $platformCommission += $comm;
                $entries[] = [
                    'id' => 'comm_' . $order->id,
                    'entry_type' => 'PLATFORM_COMMISSION',
                    'direction' => 'debit',
                    'amount' => $comm,
                    'description' => 'Bagi hasil platform pesanan ' . $order->order_number,
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'created_by_name' => 'LaundryKu Platform',
                    'created_at' => $createdAtIso,
                ];
            }
        }

        $totalIncome = $digitalPayments + $codDeposits;
        $totalExpense = $courierWages + $platformCommission;
        $netBalance = $totalIncome - $totalExpense;

        if ($request->filled('entry_type') && $request->input('entry_type') !== 'ALL') {
            $entries = array_values(array_filter($entries, fn ($e) => $e['entry_type'] === $request->input('entry_type')));
        }
        if ($request->filled('direction') && $request->input('direction') !== 'ALL') {
            $entries = array_values(array_filter($entries, fn ($e) => $e['direction'] === $request->input('direction')));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'store_id' => (string) $store->id,
                    'store_name' => $store->name,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'net_balance' => $netBalance,
                    'breakdown' => [
                        'digital_payments' => $digitalPayments,
                        'cod_deposits' => $codDeposits,
                        'courier_wages' => $courierWages,
                        'platform_commission' => $platformCommission,
                    ],
                    'total_transactions' => count($entries),
                ],
                'entries' => $entries,
            ],
        ]);
    }

    /**
     * GET /api/v1/store-admin/couriers
     */
    public function couriers(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $assigned = Courier::where('store_id', $store->id)
            ->where('is_verified', true)
            ->with('user')
            ->get()
            ->map(function ($c) {
                $rawCreated = $c->getRawOriginal('created_at');
                return [
                    'assignment_id' => (string) $c->id,
                    'courier_profile_id' => (string) $c->id,
                    'user_id' => (string) $c->user_id,
                    'name' => $c->user?->name ?? 'Kurir',
                    'phone' => $c->user?->phone,
                    'email' => $c->user?->email,
                    'vehicle_type' => $c->vehicle_type ?? 'Motor',
                    'status' => $c->is_available ? 'active' : 'inactive',
                    'started_at' => $this->formatTimestamp($rawCreated, $c->created_at),
                    'ended_at' => null,
                    'recruited_by_name' => 'Owner',
                ];
            });

        $rejectedIds = AuditLog::where('action', 'STORE_COURIER_REJECT')
            ->where('resource_type', 'courier')
            ->where(function ($q) use ($user, $store) {
                $q->where('actor_id', $user->id)
                  ->orWhere('after_value', 'like', '%"store_id":' . $store->id . '%')
                  ->orWhere('after_value', 'like', '%"store_id":"' . $store->id . '"%');
            })
            ->pluck('resource_id')
            ->toArray();

        $candidatesQuery = Courier::where(function ($q) use ($store) {
            $q->where(function ($sub) use ($store) {
                $sub->where('store_id', $store->id)
                    ->where('is_verified', false);
            })->orWhere(function ($sub) {
                $sub->whereNull('store_id')
                    ->where('is_verified', true);
            });
        });

        if (! empty($rejectedIds)) {
            $candidatesQuery->whereNotIn('id', $rejectedIds);
        }

        $candidates = $candidatesQuery
            ->with('user')
            ->get()
            ->map(function ($c) {
                $rawCreated = $c->getRawOriginal('created_at');
                return [
                    'courier_profile_id' => (string) $c->id,
                    'user_id' => (string) $c->user_id,
                    'name' => $c->user?->name ?? 'Pelamar Kurir',
                    'phone' => $c->user?->phone,
                    'email' => $c->user?->email,
                    'vehicle_type' => $c->vehicle_type ?? 'Motor',
                    'verification_status' => $c->is_verified ? 'verified' : 'pending',
                    'applied_at' => $this->formatTimestamp($rawCreated, $c->created_at),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'store' => [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                ],
                'assigned_couriers' => $assigned,
                'available_candidates' => $candidates,
            ],
        ]);
    }

    /**
     * POST /api/v1/store-admin/couriers/recruit/{courierProfileId}
     */
    public function recruitCourier(Request $request, $courierProfileId): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $courier = Courier::findOrFail($courierProfileId);

        if ($courier->store_id && (int) $courier->store_id !== (int) $store->id && $courier->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Kurir sudah terikat dan bertugas pada toko lain.',
            ], 422);
        }

        $courier->store_id = $store->id;
        $courier->is_verified = true;
        $courier->is_available = true;
        $courier->save();

        if ($courier->user) {
            if ($courier->user->role !== User::ROLE_COURIER && $courier->user->role !== User::ROLE_SUPER_ADMIN) {
                $courier->user->role = User::ROLE_COURIER;
                $courier->user->save();
            }
            $courierRole = Role::where('name', 'courier')->first();
            if ($courierRole && ! $courier->user->roles()->where('roles.id', $courierRole->id)->exists()) {
                $courier->user->roles()->attach($courierRole->id, [
                    'store_id' => $store->id,
                    'assigned_at' => now(),
                    'assigned_by' => $user->id,
                ]);
            }
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_COURIER_RECRUIT',
            'resource_type' => 'courier',
            'resource_id' => $courier->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['store_id' => $store->id]),
        ]);

        NotificationTrigger::courierRecruited($courier, $store);

        return response()->json([
            'success' => true,
            'message' => 'Kurir berhasil direkrut dan ditugaskan ke toko Anda.',
            'data' => [
                'courier_profile_id' => (string) $courier->id,
                'store_id' => (string) $store->id,
                'is_assigned' => true,
            ],
        ]);
    }

    /**
     * POST /api/v1/store-admin/couriers/terminate/{assignmentId}
     */
    public function terminateCourier(Request $request, $assignmentId): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $courier = Courier::where('store_id', $store->id)->findOrFail($assignmentId);
        $courier->store_id = null;
        $courier->is_available = false;
        $courier->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_COURIER_TERMINATE',
            'resource_type' => 'courier',
            'resource_id' => $courier->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['store_id' => null]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan kurir di toko Anda berhasil dihentikan.',
            'data' => [
                'courier_profile_id' => (string) $courier->id,
                'is_assigned' => false,
            ],
        ]);
    }

    /**
     * POST /api/v1/store-admin/couriers/reject/{courierProfileId}
     */
    public function rejectCourier(Request $request, $courierProfileId): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $courier = Courier::find($courierProfileId);
        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Kandidat kurir tidak ditemukan.',
            ], 404);
        }

        if ($courier->store_id !== null && (int) $courier->store_id !== (int) $store->id && $courier->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk menolak pelamar kurir dari toko lain.',
            ], 403);
        }

        $reason = $request->input('reason', 'Lamaran tidak memenuhi kualifikasi toko.');

        // Putus keterikatan jika pelamar mendaftar ke toko ini
        if ($courier->store_id !== null) {
            $courier->store_id = null;
            $courier->is_verified = false;
            $courier->is_available = false;
            $courier->save();
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_COURIER_REJECT',
            'resource_type' => 'courier',
            'resource_id' => $courier->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode([
                'store_id' => $store->id,
                'store_name' => $store->name,
                'reason' => $reason,
                'rejected_at' => now()->toISOString(),
            ]),
        ]);

        NotificationTrigger::courierRejected($courier, $store, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Lamaran kurir ditolak.',
            'data' => [
                'courier_profile_id' => (string) $courier->id,
                'rejection_reason' => $reason,
            ],
        ]);
    }

    /**
     * GET /api/v1/store-admin/ratings
     */
    public function ratings(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $query = Review::where('store_id', $store->id)
            ->with(['customer:id,name', 'order:id,order_number']);

        if ($request->filled('score')) {
            $query->where('rating', (int) $request->input('score'));
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 10)));
        $paginated = $query->latest('created_at')->paginate($perPage);

        $allReviews = Review::where('store_id', $store->id)->get();
        $totalRatings = $allReviews->count();
        $avgScore = $totalRatings > 0 ? round($allReviews->avg('rating'), 1) : 0;

        $items = collect($paginated->items())->map(function ($r) use ($store) {
            $rawCreated = $r->getRawOriginal('created_at');
            $rawUpdated = $r->getRawOriginal('updated_at');

            return [
                'id' => (string) $r->id,
                'order_id' => (string) ($r->order_id ?? ''),
                'order_number' => $r->order?->order_number ?? ('ORD-' . $r->id),
                'customer_id' => (string) ($r->customer_user_id ?? $r->customer_id ?? ''),
                'customer_name' => $r->customer?->name ?? 'Pelanggan',
                'customer_avatar' => null,
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'score' => (int) $r->rating,
                'review' => $r->comment,
                'created_at' => $this->formatTimestamp($rawCreated, $r->created_at),
                'updated_at' => $this->formatTimestamp($rawUpdated, $r->updated_at),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'summary' => [
                    'average_score' => $avgScore,
                    'total_ratings' => $totalRatings,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/store-admin/complaints
     */
    public function complaints(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $query = Dispute::whereHas('order', fn ($q) => $q->where('store_id', $store->id))
            ->with(['order.customer:id,name', 'order.store:id,name', 'order:id,order_number,store_id,customer_id', 'openedBy:id,name']);

        if ($request->filled('status')) {
            $frontendStatus = strtoupper($request->input('status'));
            $statusReverse = [
                'OPEN' => Dispute::STATUS_COMPLAINT_SUBMITTED,
                'IN_INVESTIGATION' => [Dispute::STATUS_UNDER_REVIEW, Dispute::STATUS_INVESTIGATION],
                'RESOLVED' => Dispute::STATUS_RESOLVED,
                'REJECTED' => Dispute::STATUS_REJECTED,
            ];
            if (isset($statusReverse[$frontendStatus])) {
                $target = $statusReverse[$frontendStatus];
                if (is_array($target)) {
                    $query->whereIn('status', $target);
                } else {
                    $query->where('status', $target);
                }
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('complaint_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 10)));
        $paginated = $query->latest('created_at')->paginate($perPage);

        $statusMap = [
            Dispute::STATUS_COMPLAINT_SUBMITTED => 'OPEN',
            Dispute::STATUS_UNDER_REVIEW => 'IN_INVESTIGATION',
            Dispute::STATUS_INVESTIGATION => 'IN_INVESTIGATION',
            Dispute::STATUS_RESOLVED => 'RESOLVED',
            Dispute::STATUS_REJECTED => 'REJECTED',
        ];

        $items = collect($paginated->items())->map(function ($d) use ($statusMap, $store) {
            $rawCreated = $d->getRawOriginal('created_at');
            $rawUpdated = $d->getRawOriginal('updated_at');
            $rawResolved = $d->getRawOriginal('resolved_at');

            return [
                'id' => (string) $d->id,
                'order_id' => (string) $d->order_id,
                'order_number' => $d->order?->order_number ?? $d->complaint_number,
                'customer_id' => (string) ($d->order?->customer_id ?? $d->opened_by),
                'customer_name' => $d->order?->customer?->name ?? ($d->openedBy?->name ?? 'Pelanggan'),
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'category' => $d->reason ?? 'OTHER',
                'description' => $d->description,
                'status' => $statusMap[$d->status] ?? 'OPEN',
                'resolution_note' => $d->resolution_note,
                'resolved_at' => $this->formatTimestamp($rawResolved, $d->resolved_at),
                'resolved_by' => $d->resolved_by ? (string) $d->resolved_by : null,
                'resolver_name' => null,
                'attachments' => is_array($d->evidence) ? $d->evidence : [],
                'created_at' => $this->formatTimestamp($rawCreated, $d->created_at),
                'updated_at' => $this->formatTimestamp($rawUpdated, $d->updated_at),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/store-admin/complaints/{id}/status
     */
    public function updateComplaintStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $dispute = Dispute::whereHas('order', fn ($q) => $q->where('store_id', $store->id))->findOrFail($id);

        $statusInput = strtoupper($request->input('status', 'OPEN'));
        $statusMapping = [
            'OPEN' => Dispute::STATUS_COMPLAINT_SUBMITTED,
            'IN_INVESTIGATION' => Dispute::STATUS_INVESTIGATION,
            'RESOLVED' => Dispute::STATUS_RESOLVED,
            'REJECTED' => Dispute::STATUS_REJECTED,
        ];

        $targetStatus = $statusMapping[$statusInput] ?? Dispute::STATUS_COMPLAINT_SUBMITTED;
        $dispute->status = $targetStatus;

        if ($request->has('resolution_note')) {
            $dispute->resolution_note = $request->input('resolution_note');
        }

        if (in_array($targetStatus, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true)) {
            $dispute->resolved_at = now();
            $dispute->resolved_by = $user->id;
        }

        $dispute->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'STORE_COMPLAINT_STATUS_UPDATE',
            'resource_type' => 'dispute',
            'resource_id' => $dispute->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['status' => $targetStatus, 'resolution_note' => $dispute->resolution_note]),
        ]);

        $rawCreated = $dispute->getRawOriginal('created_at');
        $rawUpdated = $dispute->getRawOriginal('updated_at');
        $rawResolved = $dispute->getRawOriginal('resolved_at');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $dispute->id,
                'order_id' => (string) $dispute->order_id,
                'order_number' => $dispute->order?->order_number ?? $dispute->complaint_number,
                'customer_id' => (string) ($dispute->order?->customer_id ?? $dispute->opened_by),
                'customer_name' => $dispute->order?->customer?->name ?? 'Pelanggan',
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'category' => $dispute->reason ?? 'OTHER',
                'description' => $dispute->description,
                'status' => $statusInput,
                'resolution_note' => $dispute->resolution_note,
                'resolved_at' => $this->formatTimestamp($rawResolved, $dispute->resolved_at),
                'resolved_by' => $dispute->resolved_by ? (string) $dispute->resolved_by : null,
                'resolver_name' => $user->name,
                'attachments' => is_array($dispute->evidence) ? $dispute->evidence : [],
                'created_at' => $this->formatTimestamp($rawCreated, $dispute->created_at),
                'updated_at' => $this->formatTimestamp($rawUpdated, $dispute->updated_at),
            ],
        ]);
    }

    /**
     * GET /api/v1/store/payment-methods & /api/v1/mitra/payment-methods
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user, $request->header('X-Store-ID', $request->input('store_id')));

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan atau Anda tidak memiliki akses.',
            ], 404);
        }

        $supportedBanks = ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'];

        return response()->json([
            'success' => true,
            'data' => [
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'cod_enabled' => (bool) ($store->cod_enabled ?? true),
                'qris_enabled' => (bool) ($store->qris_enabled ?? false),
                'va_enabled' => (bool) ($store->va_enabled ?? false),
                'va_banks' => $store->va_banks ?? $supportedBanks,
                'supported_banks' => $supportedBanks,
            ],
        ]);
    }

    /**
     * PUT /api/v1/store/payment-methods & /api/v1/mitra/payment-methods
     */
    public function updatePaymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user, $request->header('X-Store-ID', $request->input('store_id')));

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan atau Anda tidak memiliki akses.',
            ], 404);
        }

        $validated = $request->validate([
            'cod_enabled' => 'nullable|boolean',
            'qris_enabled' => 'nullable|boolean',
            'va_enabled' => 'nullable|boolean',
            'va_banks' => 'nullable|array',
            'va_banks.*' => 'string|in:BCA,BNI,BRI,MANDIRI,PERMATA',
        ]);

        $codEnabled = array_key_exists('cod_enabled', $validated) ? (bool) $validated['cod_enabled'] : (bool) $store->cod_enabled;
        $qrisEnabled = array_key_exists('qris_enabled', $validated) ? (bool) $validated['qris_enabled'] : (bool) $store->qris_enabled;
        $vaEnabled = array_key_exists('va_enabled', $validated) ? (bool) $validated['va_enabled'] : (bool) $store->va_enabled;

        // Minimal satu aktif
        if (!$codEnabled && !$qrisEnabled && !$vaEnabled) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal satu metode pembayaran harus aktif.',
                'error' => [
                    'code' => 'MINIMUM_ONE_PAYMENT_METHOD',
                    'message' => 'Minimal satu metode pembayaran harus aktif.',
                ],
            ], 422);
        }

        if ($vaEnabled) {
            $vaBanks = $validated['va_banks'] ?? $store->va_banks ?? ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'];
            if (empty($vaBanks)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu bank untuk Virtual Account.',
                    'error' => [
                        'code' => 'VA_BANK_REQUIRED',
                        'message' => 'Pilih minimal satu bank untuk Virtual Account.',
                    ],
                ], 422);
            }
        } else {
            $vaBanks = $validated['va_banks'] ?? $store->va_banks;
        }

        $beforeValue = [
            'cod_enabled' => (bool) $store->cod_enabled,
            'qris_enabled' => (bool) $store->qris_enabled,
            'va_enabled' => (bool) $store->va_enabled,
            'va_banks' => $store->va_banks,
        ];

        $store->cod_enabled = $codEnabled;
        $store->qris_enabled = $qrisEnabled;
        $store->va_enabled = $vaEnabled;
        $store->va_banks = $vaBanks;
        $store->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role ?? $user->roles()->first()?->name ?? 'store_admin',
            'action' => 'STORE_PAYMENT_METHODS_UPDATE',
            'resource_type' => 'store',
            'resource_id' => $store->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'before_value' => json_encode($beforeValue),
            'after_value' => json_encode([
                'cod_enabled' => $store->cod_enabled,
                'qris_enabled' => $store->qris_enabled,
                'va_enabled' => $store->va_enabled,
                'va_banks' => $store->va_banks,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan metode pembayaran toko berhasil diperbarui.',
            'data' => [
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'cod_enabled' => (bool) $store->cod_enabled,
                'qris_enabled' => (bool) $store->qris_enabled,
                'va_enabled' => (bool) $store->va_enabled,
                'va_banks' => $store->va_banks ?? ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'],
                'supported_banks' => ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'],
            ],
        ]);
    }

    /**
    /**
     * GET /api/v1/store/payout-account & /api/store/payout-account
     */
    public function getPayoutAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Ambil store_id dari session (pemilik toko)
        $store = Store::where('owner_user_id', $user->id)->first();
        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan pemilik toko.',
            ], 403);
        }

        $account = StorePayoutAccount::where('store_id', $store->id)->first();

        return response()->json([
            'success' => true,
            'data' => $account ? [
                'id' => $account->id,
                'store_id' => (string) $store->id,
                'bank_code' => $account->bank_code,
                'account_number' => $account->account_number,
                'account_number_masked' => '****' . substr($account->account_number, -4),
                'account_holder_name' => $account->account_holder_name,
                'created_at' => $account->created_at?->toISOString(),
                'updated_at' => $account->updated_at?->toISOString(),
            ] : null,
        ]);
    }

    /**
     * PUT /api/v1/store/payout-account & /api/store/payout-account
     */
    public function updatePayoutAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Tolak jika bukan pemilik toko
        $store = Store::where('owner_user_id', $user->id)->first();
        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan pemilik toko.',
            ], 403);
        }

        $validated = $request->validate([
            'bank_code' => 'required|string|in:BCA,BNI,BRI,MANDIRI,PERMATA,BSI,CIMB,DANAMON,BTN,DANA,LAINNYA',
            'account_number' => 'required|digits_between:8,20',
            'account_holder_name' => 'required|string|max:100',
        ], [
            'bank_code.required' => 'Bank harus dipilih dari daftar yang tersedia.',
            'bank_code.in' => 'Bank yang dipilih tidak valid.',
            'account_number.required' => 'Nomor rekening wajib diisi.',
            'account_number.digits_between' => 'Nomor rekening harus berupa angka 8–20 digit.',
            'account_holder_name.max' => 'Nama pemilik rekening maksimal 100 karakter.',
            'account_holder_name.required' => 'Nama pemilik rekening wajib diisi.',
        ]);

        $account = StorePayoutAccount::updateOrCreate(
            ['store_id' => $store->id],
            [
                'bank_code' => strtoupper($validated['bank_code']),
                'account_number' => $validated['account_number'],
                'account_holder_name' => strtoupper(trim($validated['account_holder_name'])),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Rekening pencairan dana berhasil disimpan.',
            'data' => [
                'id' => $account->id,
                'store_id' => (string) $store->id,
                'bank_code' => $account->bank_code,
                'account_number' => $account->account_number,
                'account_number_masked' => '****' . substr($account->account_number, -4),
                'account_holder_name' => $account->account_holder_name,
                'created_at' => $account->created_at?->toISOString(),
                'updated_at' => $account->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * POST /api/v1/store/orders/{order}/confirm-cod
     */
    public function confirmCod(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $store = $this->resolveStore($user, (string) $order->store_id);

        if (! $store || (int) $order->store_id !== (int) $store->id) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan atau tidak berada di gerai Anda.',
            ], 404);
        }

        if (strtolower((string) $order->payment_method) !== 'cod') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini bukan pesanan dengan metode pembayaran COD.',
            ], 422);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran COD pesanan sudah berstatus lunas.',
            ], 422);
        }

        $order->payment_status = 'paid';
        $order->paid_at = now();
        $order->save();

        $payment = Payment::where('order_id', $order->id)->first();
        if ($payment) {
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role ?? $user->roles()->first()?->name ?? 'store_admin',
            'action' => 'STORE_CONFIRM_COD',
            'resource_type' => 'order',
            'resource_id' => $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'after_value' => json_encode(['payment_status' => 'paid', 'amount' => $order->total_amount]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran COD pesanan berhasil dikonfirmasi oleh toko.',
            'data' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'payment_status' => 'PAID',
                'collected_amount' => (float) ($order->total_amount ?? $order->final_price ?? 0),
                'collected_by' => $user->name,
            ],
        ]);
    }
}