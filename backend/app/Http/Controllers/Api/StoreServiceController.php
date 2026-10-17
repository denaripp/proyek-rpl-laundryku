<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\StoreService;
use App\Services\AuditLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreServiceController extends Controller
{
    use ApiResponse;

    /**
     * List all services for a store owned by authenticated store admin.
     * Supports pagination, search, category filter, status filter, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Resolve store: either query param store_id or first owned store
        $storeId = $request->query('store_id');
        $store = $storeId
            ? Store::where('id', $storeId)->where('owner_user_id', $user->id)->first()
            : Store::where('owner_user_id', $user->id)->first();

        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_FOUND',
                'Anda belum memiliki toko terdaftar atau toko tidak ditemukan.',
                null,
                404
            );
        }

        $query = StoreService::where('store_id', $store->id)
            ->with('category:id,name,slug');

        // Filter by Search (Name)
        $search = $request->query('search', $request->query('q'));
        if ($search !== null && trim($search) !== '') {
            $query->where('name', 'like', '%' . trim($search) . '%');
        }

        // Filter by Category
        if ($request->filled('category_id') && $request->query('category_id') !== 'all') {
            $query->where('category_id', $request->query('category_id'));
        }

        // Filter by Active Status
        if ($request->has('is_active') && $request->query('is_active') !== '' && $request->query('is_active') !== 'all') {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        } elseif ($request->filled('status') && $request->query('status') !== 'all') {
            if ($request->query('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->query('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Sorting
        $sort = $request->query('sort', 'latest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'name_asc':
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'price_asc':
            case 'price_low':
                $query->orderBy('price_per_unit', 'asc');
                break;
            case 'price_desc':
            case 'price_high':
                $query->orderBy('price_per_unit', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Total counts for dashboard badge counters
        $allStoreServices = StoreService::where('store_id', $store->id);
        $totalCount = (clone $allStoreServices)->count();
        $activeCount = (clone $allStoreServices)->where('is_active', true)->count();
        $inactiveCount = $totalCount - $activeCount;

        // Check if pagination is requested
        if ($request->has('page') || $request->has('per_page')) {
            $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
            $paginator = $query->paginate($perPage);

            $formatted = collect($paginator->items())->map(function (StoreService $svc) {
                return $this->formatService($svc);
            });

            return $this->successResponse(
                data: $formatted,
                meta: [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'total' => $paginator->total(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'active_count' => $activeCount,
                    'inactive_count' => $inactiveCount,
                ]
            );
        }

        $services = $query->get();
        $formatted = $services->map(function (StoreService $svc) {
            return $this->formatService($svc);
        });

        return $this->successResponse(
            data: $formatted,
            meta: [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'total' => $totalCount,
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
            ]
        );
    }

    /**
     * Create a new store service.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Resolve store: either query param or first owned store
        $storeId = $request->input('store_id');
        $store = $storeId
            ? Store::where('id', $storeId)->where('owner_user_id', $user->id)->first()
            : Store::where('owner_user_id', $user->id)->first();

        if (!$store) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki kewenangan mengelola layanan di toko ini atau toko tidak ditemukan.',
                null,
                403
            );
        }

        if ($store->approval_status !== 'approved') {
            return $this->errorResponse(
                'STORE_NOT_APPROVED',
                'Layanan hanya dapat ditambahkan setelah toko disetujui oleh admin platform.',
                null,
                422
            );
        }

        $validator = Validator::make($request->all(), [
            'category_id' => [
                'required',
                'uuid',
                Rule::exists('service_categories', 'id')->where(function ($q) {
                    $q->where('status', 'active');
                }),
            ],
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('store_services', 'name')->where('store_id', $store->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'pricing_unit' => ['required', 'string', 'in:kg,item'],
            'price_per_unit' => ['required', 'integer', 'min:0'],
            'minimum_charge' => ['nullable', 'integer', 'min:0'],
            'estimated_duration_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'category_id.required' => 'Kategori layanan wajib dipilih.',
            'category_id.exists' => 'Kategori layanan tidak valid atau tidak aktif.',
            'name.required' => 'Nama layanan wajib diisi.',
            'name.min' => 'Nama layanan minimal 3 karakter.',
            'name.max' => 'Nama layanan maksimal 100 karakter.',
            'name.unique' => 'Nama layanan sudah digunakan di toko Anda.',
            'description.max' => 'Deskripsi layanan maksimal 500 karakter.',
            'pricing_unit.in' => 'Satuan tarif harus berupa kg atau item.',
            'price_per_unit.min' => 'Tarif per unit tidak boleh negatif.',
            'price_per_unit.required' => 'Tarif per unit wajib diisi.',
            'minimum_charge.min' => 'Biaya minimum tidak boleh bernilai negatif.',
            'estimated_duration_hours.min' => 'Estimasi pengerjaan minimal 1 jam.',
            'estimated_duration_hours.max' => 'Estimasi pengerjaan maksimal 168 jam (7 hari).',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Input layanan toko tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $validated = $validator->validated();

        $service = StoreService::create([
            'store_id' => $store->id,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'pricing_unit' => $validated['pricing_unit'],
            'price_per_unit' => $validated['price_per_unit'],
            'minimum_charge' => $validated['minimum_charge'] ?? 0,
            'estimated_duration_hours' => $validated['estimated_duration_hours'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $service->load('category:id,name,slug');

        AuditLogService::logStoreService(
            $service,
            'STORE_SERVICE_CREATED',
            $user,
            ['action_details' => 'Membuat layanan toko baru']
        );

        return $this->successResponse(
            data: $this->formatService($service),
            meta: [
                'message' => 'Layanan toko berhasil ditambahkan.',
            ],
            status: 201
        );
    }

    /**
     * Show detail of a single store service.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $service = StoreService::with(['store', 'category:id,name,slug'])->find($id);

        if (!$service) {
            return $this->errorResponse(
                'NOT_FOUND',
                'Layanan toko tidak ditemukan.',
                null,
                404
            );
        }

        if ($service->store->owner_user_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki akses ke layanan toko ini.',
                null,
                403
            );
        }

        return $this->successResponse(
            data: $this->formatService($service)
        );
    }

    /**
     * Update an existing store service.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $service = StoreService::with('store')->find($id);

        if (!$service) {
            return $this->errorResponse(
                'NOT_FOUND',
                'Layanan toko tidak ditemukan.',
                null,
                404
            );
        }

        if ($service->store->owner_user_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak berhak mengubah layanan milik toko lain.',
                null,
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'category_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('service_categories', 'id')->where(function ($q) {
                    $q->where('status', 'active');
                }),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('store_services', 'name')->where('store_id', $service->store_id)->ignore($service->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'pricing_unit' => ['sometimes', 'required', 'string', 'in:kg,item'],
            'price_per_unit' => ['sometimes', 'required', 'integer', 'min:0'],
            'minimum_charge' => ['nullable', 'integer', 'min:0'],
            'estimated_duration_hours' => ['sometimes', 'required', 'integer', 'min:1', 'max:168'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'category_id.exists' => 'Kategori layanan tidak valid atau tidak aktif.',
            'name.min' => 'Nama layanan minimal 3 karakter.',
            'name.max' => 'Nama layanan maksimal 100 karakter.',
            'name.unique' => 'Nama layanan sudah digunakan di toko Anda.',
            'description.max' => 'Deskripsi layanan maksimal 500 karakter.',
            'pricing_unit.in' => 'Satuan tarif harus berupa kg atau item.',
            'price_per_unit.min' => 'Tarif per unit tidak boleh negatif.',
            'minimum_charge.min' => 'Biaya minimum tidak boleh bernilai negatif.',
            'estimated_duration_hours.min' => 'Estimasi pengerjaan minimal 1 jam.',
            'estimated_duration_hours.max' => 'Estimasi pengerjaan maksimal 168 jam (7 hari).',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Pembaruan layanan toko gagal.',
                $validator->errors()->toArray(),
                422
            );
        }

        $service->update($validator->validated());
        $service->load('category:id,name,slug');

        AuditLogService::logStoreService(
            $service,
            'STORE_SERVICE_UPDATED',
            $user,
            ['action_details' => 'Memperbarui data layanan toko']
        );

        return $this->successResponse(
            data: $this->formatService($service),
            meta: [
                'message' => 'Layanan toko berhasil diperbarui.',
            ]
        );
    }

    /**
     * Update active/inactive status of a service.
     * Supports both explicit boolean is_active and toggle.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $service = StoreService::with('store')->find($id);

        if (!$service) {
            return $this->errorResponse(
                'NOT_FOUND',
                'Layanan toko tidak ditemukan.',
                null,
                404
            );
        }

        if ($service->store->owner_user_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki hak untuk mengubah status layanan ini.',
                null,
                403
            );
        }

        if ($request->has('is_active')) {
            $service->is_active = $request->boolean('is_active');
        } else {
            $service->is_active = !$service->is_active;
        }

        $service->save();
        $service->load('category:id,name,slug');

        AuditLogService::logStoreService(
            $service,
            $service->is_active ? 'STORE_SERVICE_ACTIVATED' : 'STORE_SERVICE_DEACTIVATED',
            $user,
            ['is_active' => $service->is_active]
        );

        return $this->successResponse(
            data: $this->formatService($service),
            meta: [
                'message' => $service->is_active ? 'Layanan berhasil diaktifkan.' : 'Layanan berhasil dinonaktifkan.',
            ]
        );
    }

    /**
     * Alias for updateStatus for backward compatibility with toggle-status.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        return $this->updateStatus($request, $id);
    }

    /**
     * Bulk update active status for multiple services belonging to the store.
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        // Resolve store
        $storeId = $request->input('store_id');
        $store = $storeId
            ? Store::where('id', $storeId)->where('owner_user_id', $user->id)->first()
            : Store::where('owner_user_id', $user->id)->first();

        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_FOUND',
                'Anda belum memiliki toko terdaftar atau toko tidak ditemukan.',
                null,
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'uuid', 'exists:store_services,id'],
            'is_active' => ['required', 'boolean'],
        ], [
            'ids.required' => 'Pilih minimal satu layanan untuk diubah statusnya.',
            'ids.max' => 'Maksimal 50 layanan yang dapat diubah sekaligus.',
            'is_active.required' => 'Status aktif wajib disertakan.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Data aksi massal tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $ids = $request->input('ids');
        $isActive = $request->boolean('is_active');

        // Verify all IDs belong to user's store
        $ownedCount = StoreService::where('store_id', $store->id)
            ->whereIn('id', $ids)
            ->count();

        if ($ownedCount !== count($ids)) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Terdapat layanan yang bukan milik toko Anda atau tidak ditemukan.',
                null,
                403
            );
        }

        // Perform bulk update
        StoreService::where('store_id', $store->id)
            ->whereIn('id', $ids)
            ->update([
                'is_active' => $isActive,
                'updated_at' => now(),
            ]);

        // Audit Log
        \App\Models\AuditLog::record(
            actor: $user,
            action: 'STORE_SERVICES_BULK_STATUS_UPDATED',
            entityType: 'store_services',
            entityId: $store->id,
            metadata: [
                'store_id' => $store->id,
                'ids' => $ids,
                'count' => count($ids),
                'is_active' => $isActive,
            ]
        );

        $statusText = $isActive ? 'diaktifkan' : 'dinonaktifkan';

        return $this->successResponse(
            data: [
                'updated_count' => count($ids),
                'is_active' => $isActive,
            ],
            meta: [
                'message' => count($ids) . " layanan berhasil {$statusText}.",
            ]
        );
    }

    /**
     * Delete a store service.
     * Rejects deletion if service has been used in order_items.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $service = StoreService::with('store')->find($id);

        if (!$service) {
            return $this->errorResponse(
                'NOT_FOUND',
                'Layanan toko tidak ditemukan.',
                null,
                404
            );
        }

        if ($service->store->owner_user_id !== $user->id && !$user->hasRole('super_admin')) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Anda tidak memiliki hak untuk menghapus layanan ini.',
                null,
                403
            );
        }

        // Safety check: has this service been used in any order items?
        $isUsedInOrders = OrderItem::where('service_id', $service->id)->exists();
        if ($isUsedInOrders) {
            return $this->errorResponse(
                'CANNOT_DELETE_USED_SERVICE',
                'Layanan ini sudah memiliki riwayat pesanan dan tidak dapat dihapus. Silakan nonaktifkan layanan jika sudah tidak digunakan.',
                [
                    'service_id' => $service->id,
                    'suggestion' => 'deactivate',
                ],
                422
            );
        }

        // Log delete before deleting
        AuditLogService::logStoreService(
            $service,
            'STORE_SERVICE_DELETED',
            $user,
            ['action_details' => 'Menghapus layanan toko']
        );

        $service->delete();

        return $this->successResponse(
            data: [
                'id' => $id,
                'deleted' => true,
            ],
            meta: [
                'message' => 'Layanan toko berhasil dihapus.',
            ]
        );
    }

    /**
     * Helper to format StoreService data structure.
     */
    protected function formatService(StoreService $service): array
    {
        return [
            'id' => $service->id,
            'store_id' => $service->store_id,
            'category_id' => $service->category_id,
            'category_name' => $service->category?->name,
            'category_slug' => $service->category?->slug,
            'name' => $service->name,
            'description' => $service->description,
            'pricing_unit' => $service->pricing_unit,
            'price_per_unit' => (int) $service->price_per_unit,
            'minimum_charge' => (int) $service->minimum_charge,
            'estimated_duration_hours' => (int) $service->estimated_duration_hours,
            'is_active' => (bool) $service->is_active,
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }
}
