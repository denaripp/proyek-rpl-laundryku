<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Store;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminStoreController extends Controller
{
    use ApiResponse;

    /**
     * List all stores with filters (approval_status, operational_status, search).
     * FR-ADMIN-02, US-ADMIN-02
     */
    public function index(Request $request): JsonResponse
    {
        $query = Store::with(['owner', 'documents', 'services']);

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->query('approval_status'));
        }

        if ($request->filled('operational_status')) {
            $query->where('operational_status', $request->query('operational_status'));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->query('q') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('address_text', 'LIKE', $search)
                  ->orWhereHas('owner', function ($uq) use ($search) {
                      $uq->where('name', 'LIKE', $search)
                         ->orWhere('email', 'LIKE', $search)
                         ->orWhere('phone', 'LIKE', $search);
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $stores = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = collect($stores->items())->map(fn ($store) => $this->formatStore($store));

        return $this->successResponse(
            data: $formatted,
            meta: [
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
                'per_page' => $stores->perPage(),
                'total' => $stores->total(),
            ]
        );
    }

    /**
     * Get store detail.
     */
    public function show(string $id): JsonResponse
    {
        $store = Store::with(['owner', 'documents', 'services.category', 'approver'])->find($id);

        if (!$store) {
            return $this->errorResponse('NOT_FOUND', 'Toko tidak ditemukan.', null, 404);
        }

        return $this->successResponse(
            data: $this->formatStore($store, true)
        );
    }

    /**
     * Approve store application.
     * FR-ADMIN-02, US-ADMIN-02
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $store = Store::with('owner')->find($id);

        if (!$store) {
            return $this->errorResponse('NOT_FOUND', 'Toko tidak ditemukan.', null, 404);
        }

        $oldStatus = $store->approval_status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($store, $user, $oldStatus, $request) {
            $store->update([
                'approval_status' => 'approved',
                'operational_status' => 'active',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            // Assign / activate store_admin role scoped to this store_id idempotently
            $storeAdminRole = \App\Models\Role::firstOrCreate(
                ['code' => 'store_admin'],
                ['name' => 'Admin Toko', 'level' => 50]
            );

            \App\Models\UserRole::updateOrCreate(
                [
                    'user_id' => $store->owner_user_id,
                    'role_id' => $storeAdminRole->id,
                    'store_id' => $store->id,
                ],
                [
                    'status' => 'active',
                ]
            );

            // Record Audit Log (FR-ADMIN-10, US-ADMIN-08)
            AuditLog::record(
                actor: $user,
                action: 'APPROVE_STORE',
                entityType: 'stores',
                entityId: $store->id,
                metadata: [
                    'store_name' => $store->name,
                    'owner_id' => $store->owner_user_id,
                    'previous_approval_status' => $oldStatus,
                ],
                ip: $request->ip()
            );

            // Notify store owner
            if ($store->owner_user_id) {
                Notification::create([
                    'user_id' => $store->owner_user_id,
                    'channel' => 'in_app',
                    'event_type' => 'STORE_APPROVED',
                    'title' => 'Pengajuan Toko Disetujui! 🎉',
                    'message' => "Selamat! Toko '{$store->name}' telah disetujui oleh Super Admin. Toko Anda sekarang dapat menerima pesanan laundry.",
                    'payload' => ['store_id' => $store->id, 'action' => 'approve'],
                    'status' => 'unread',
                ]);
            }
        });

        return $this->successResponse(
            data: $this->formatStore($store->fresh(['owner', 'approver', 'documents', 'services'])),
            meta: ['message' => "Toko '{$store->name}' berhasil disetujui dan siap beroperasi."]
        );
    }

    /**
     * Reject store application (mandatory reason).
     * FR-ADMIN-03, US-ADMIN-02
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $store = Store::with('owner')->find($id);

        if (!$store) {
            return $this->errorResponse('NOT_FOUND', 'Toko tidak ditemukan.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan penolakan toko wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Input tidak valid.', $validator->errors()->toArray(), 422);
        }

        $reason = $request->input('reason');
        $oldStatus = $store->approval_status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($store, $user, $reason, $oldStatus, $request) {
            $store->update([
                'approval_status' => 'rejected',
                'operational_status' => 'inactive',
                'rejection_reason' => $reason,
            ]);

            // Deactivate store_admin role for this store if previously created
            $storeAdminRole = \App\Models\Role::where('code', 'store_admin')->first();
            if ($storeAdminRole) {
                \App\Models\UserRole::where('user_id', $store->owner_user_id)
                    ->where('role_id', $storeAdminRole->id)
                    ->where('store_id', $store->id)
                    ->update(['status' => 'inactive']);
            }

            // Record Audit Log (FR-ADMIN-10, US-ADMIN-08)
            AuditLog::record(
                actor: $user,
                action: 'REJECT_STORE',
                entityType: 'stores',
                entityId: $store->id,
                metadata: [
                    'store_name' => $store->name,
                    'owner_id' => $store->owner_user_id,
                    'previous_approval_status' => $oldStatus,
                    'reason' => $reason,
                ],
                ip: $request->ip()
            );

            // Notify store owner
            if ($store->owner_user_id) {
                Notification::create([
                    'user_id' => $store->owner_user_id,
                    'channel' => 'in_app',
                    'event_type' => 'STORE_REJECTED',
                    'title' => 'Pengajuan Toko Ditolak',
                    'message' => "Mohon maaf, pengajuan toko '{$store->name}' ditolak oleh admin. Alasan: {$reason}",
                    'payload' => ['store_id' => $store->id, 'reason' => $reason],
                    'status' => 'unread',
                ]);
            }
        });

        return $this->successResponse(
            data: $this->formatStore($store->fresh(['owner', 'documents', 'services'])),
            meta: ['message' => "Pengajuan toko '{$store->name}' telah ditolak."]
        );
    }

    /**
     * Suspend store operations (FR-ADMIN-04).
     */
    public function suspend(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $store = Store::with('owner')->find($id);

        if (!$store) {
            return $this->errorResponse('NOT_FOUND', 'Toko tidak ditemukan.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan penangguhan toko wajib diisi.',
            'reason.min' => 'Alasan penangguhan minimal 5 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Input tidak valid.', $validator->errors()->toArray(), 422);
        }

        $reason = $request->input('reason');
        $oldOperationalStatus = $store->operational_status;

        $store->update([
            'operational_status' => 'suspended',
            'rejection_reason' => $reason,
        ]);

        AuditLog::record(
            actor: $user,
            action: 'SUSPEND_STORE',
            entityType: 'stores',
            entityId: $store->id,
            metadata: [
                'store_name' => $store->name,
                'owner_id' => $store->owner_user_id,
                'previous_operational_status' => $oldOperationalStatus,
                'reason' => $reason,
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: $this->formatStore($store->fresh(['owner', 'documents', 'services'])),
            meta: ['message' => "Toko '{$store->name}' berhasil ditangguhkan."]
        );
    }

    /**
     * Unsuspend / reactivate store operations.
     */
    public function unsuspend(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $store = Store::with('owner')->find($id);

        if (!$store) {
            return $this->errorResponse('NOT_FOUND', 'Toko tidak ditemukan.', null, 404);
        }

        $oldOperationalStatus = $store->operational_status;

        $store->update([
            'operational_status' => 'active',
            'rejection_reason' => null,
        ]);

        AuditLog::record(
            actor: $user,
            action: 'REACTIVATE_STORE',
            entityType: 'stores',
            entityId: $store->id,
            metadata: [
                'store_name' => $store->name,
                'owner_id' => $store->owner_user_id,
                'previous_operational_status' => $oldOperationalStatus,
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: $this->formatStore($store->fresh(['owner', 'documents', 'services'])),
            meta: ['message' => "Toko '{$store->name}' berhasil diaktifkan kembali."]
        );
    }

    /**
     * Format store response.
     */
    protected function formatStore(Store $store, bool $detailed = false): array
    {
        $data = [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description,
            'address_text' => $store->address_text,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'service_radius_km' => $store->service_radius_km,
            'opening_hours' => $store->opening_hours,
            'approval_status' => $store->approval_status,
            'operational_status' => $store->operational_status,
            'rejection_reason' => $store->rejection_reason,
            'approved_at' => $store->approved_at?->toIso8601String(),
            'owner' => $store->owner ? [
                'id' => $store->owner->id,
                'name' => $store->owner->name,
                'email' => $store->owner->email,
                'phone' => $store->owner->phone,
            ] : null,
            'created_at' => $store->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['approver'] = $store->approver ? [
                'id' => $store->approver->id,
                'name' => $store->approver->name,
            ] : null;

            $data['documents'] = $store->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'file_url' => $doc->file_url,
                'status' => $doc->status,
                'created_at' => $doc->created_at?->toIso8601String(),
            ]);

            $data['services'] = $store->services->map(fn ($srv) => [
                'id' => $srv->id,
                'name' => $srv->name,
                'category' => $srv->category?->name,
                'pricing_unit' => $srv->pricing_unit,
                'price_per_unit' => (int) $srv->price_per_unit,
                'is_active' => (bool) $srv->is_active,
            ]);
        }

        return $data;
    }
}
