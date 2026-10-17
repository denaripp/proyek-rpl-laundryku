<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourierProfile;
use App\Models\CourierStoreAssignment;
use App\Models\Role;
use App\Models\Store;
use App\Models\UserRole;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreCourierController extends Controller
{
    use ApiResponse;

    /**
     * Get store of authenticated user.
     */
    protected function getAuthorizedStore(Request $request): ?Store
    {
        $user = $request->user();
        if ($user->hasRole('super_admin') && $request->has('store_id')) {
            return Store::find($request->input('store_id'));
        }

        return Store::where('owner_user_id', $user->id)->first();
    }

    /**
     * List couriers associated with store and available candidates (FR-COURIER-02, US-STORE-04).
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getAuthorizedStore($request);
        if (!$store) {
            return $this->errorResponse(
                'STORE_NOT_FOUND',
                'Anda belum memiliki toko laundry yang terdaftar.',
                null,
                404
            );
        }

        // Active and historical couriers assigned to this store
        $assignments = CourierStoreAssignment::where('store_id', $store->id)
            ->with(['courier.user', 'recruiter'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        // Available courier candidates applying specifically for this store
        $availableCouriers = CourierProfile::where(function ($q) use ($store) {
            $q->where('store_id', $store->id)
              ->orWhere(function ($sub) use ($store) {
                  // Fallback for mock/test profiles created without store_id
                  $sub->whereNull('store_id');
              });
        })
            ->where('verification_status', 'pending')
            ->whereDoesntHave('storeAssignments', fn ($q) => $q->where('status', 'active'))
            ->with('user')
            ->latest()
            ->get();

        return $this->successResponse([
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'assigned_couriers' => $assignments->map(fn ($assignment) => [
                'assignment_id' => $assignment->id,
                'courier_profile_id' => $assignment->courier_id,
                'user_id' => $assignment->courier?->user_id,
                'name' => $assignment->courier?->user?->name ?? 'Kurir',
                'phone' => $assignment->courier?->user?->phone,
                'email' => $assignment->courier?->user?->email,
                'vehicle_type' => $assignment->courier?->vehicle_type,
                'status' => $assignment->status,
                'started_at' => $assignment->started_at?->toIso8601String(),
                'ended_at' => $assignment->ended_at?->toIso8601String(),
                'recruited_by_name' => $assignment->recruiter?->name,
            ]),
            'available_candidates' => $availableCouriers->map(fn ($profile) => [
                'courier_profile_id' => $profile->id,
                'user_id' => $profile->user_id,
                'name' => $profile->user?->name ?? 'Kandidat Kurir',
                'phone' => $profile->user?->phone,
                'email' => $profile->user?->email,
                'vehicle_type' => $profile->vehicle_type,
                'verification_status' => $profile->verification_status,
                'applied_at' => $profile->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Recruit / approve a courier for the store (FR-COURIER-02, US-STORE-04).
     * Enforces the single-store binding constraint: one courier can only have 1 active assignment.
     */
    public function recruit(Request $request, string $courierProfileId): JsonResponse
    {
        $store = $this->getAuthorizedStore($request);
        if (!$store) {
            return $this->errorResponse('STORE_NOT_FOUND', 'Toko laundry tidak ditemukan.', null, 404);
        }

        $courier = CourierProfile::with('user')->find($courierProfileId);
        if (!$courier) {
            return $this->errorResponse('COURIER_NOT_FOUND', 'Profil kurir tidak ditemukan.', null, 404);
        }

        // Cross-store protection: candidate must have applied to this store
        if ($courier->store_id && $courier->store_id !== $store->id) {
            return $this->errorResponse(
                'UNAUTHORIZED_STORE_ACTION',
                'Anda tidak memiliki izin untuk memproses pelamar kurir dari toko lain.',
                null,
                403
            );
        }

        // CRITICAL CONSTRAINT: Check if courier already has an active assignment
        $existingActive = CourierStoreAssignment::where('courier_id', $courier->id)
            ->where('status', 'active')
            ->first();

        if ($existingActive) {
            return $this->errorResponse(
                'COURIER_ALREADY_ASSIGNED',
                'Kurir ini sudah terikat secara aktif pada toko lain. Satu kurir hanya diperbolehkan terikat pada satu toko laundry.',
                [
                    'active_store_id' => $existingActive->store_id,
                    'active_assignment_id' => $existingActive->id,
                ],
                422
            );
        }

        // TODO: Pindah toko untuk kurir aktif tidak dibuat sekarang

        $assignment = DB::transaction(function () use ($store, $courier, $request) {
            // 1. Create active assignment
            $assignment = CourierStoreAssignment::create([
                'courier_id' => $courier->id,
                'store_id' => $store->id,
                'recruited_by' => $request->user()->id,
                'status' => 'active',
                'started_at' => now(),
            ]);

            // 2. Activate courier profile
            $courier->store_id = $store->id;
            $courier->verification_status = 'approved';
            $courier->rejection_reason = null;
            $courier->status = 'active';
            $courier->save();

            // 3. Activate courier user role
            $courierRole = Role::where('code', 'courier')->first();
            $roleId = $courierRole ? $courierRole->id : 2;

            UserRole::updateOrCreate(
                [
                    'user_id' => $courier->user_id,
                    'role_id' => $roleId,
                ],
                [
                    'store_id' => $store->id,
                    'status' => 'active',
                ]
            );

            return $assignment;
        });

        return $this->successResponse([
            'assignment_id' => $assignment->id,
            'courier_id' => $courier->id,
            'courier_name' => $courier->user?->name,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'status' => $assignment->status,
            'started_at' => $assignment->started_at?->toIso8601String(),
        ], [
            'message' => "Kurir {$courier->user?->name} berhasil direkrut dan terikat aktif pada toko {$store->name}.",
        ], 201);
    }

    /**
     * Terminate / deactivate a courier's active assignment with this store (US-STORE-04).
     */
    public function terminate(Request $request, string $assignmentId): JsonResponse
    {
        $store = $this->getAuthorizedStore($request);
        if (!$store) {
            return $this->errorResponse('STORE_NOT_FOUND', 'Toko laundry tidak ditemukan.', null, 404);
        }

        $assignment = CourierStoreAssignment::where('id', $assignmentId)
            ->where('store_id', $store->id)
            ->with(['courier.user'])
            ->first();

        if (!$assignment) {
            return $this->errorResponse('ASSIGNMENT_NOT_FOUND', 'Penugasan kurir toko tidak ditemukan.', null, 404);
        }

        if ($assignment->status !== 'active') {
            return $this->errorResponse('ALREADY_INACTIVE', 'Penugasan kurir ini sudah tidak aktif.', null, 422);
        }

        DB::transaction(function () use ($assignment) {
            $assignment->status = 'inactive';
            $assignment->ended_at = now();
            $assignment->save();

            // Deactivate courier profile
            if ($assignment->courier) {
                $assignment->courier->status = 'inactive';
                $assignment->courier->save();

                // Deactivate courier user role
                $courierRole = Role::where('code', 'courier')->first();
                if ($courierRole) {
                    UserRole::where('user_id', $assignment->courier->user_id)
                        ->where('role_id', $courierRole->id)
                        ->update(['status' => 'inactive']);
                }
            }
        });

        return $this->successResponse([
            'assignment_id' => $assignment->id,
            'status' => 'inactive',
            'ended_at' => $assignment->ended_at?->toIso8601String(),
        ], [
            'message' => "Penugasan kurir {$assignment->courier?->user?->name} pada toko berhasil dinonaktifkan.",
        ]);
    }

    /**
     * Reject a courier applicant.
     */
    public function reject(Request $request, string $courierProfileId): JsonResponse
    {
        $store = $this->getAuthorizedStore($request);
        if (!$store) {
            return $this->errorResponse('STORE_NOT_FOUND', 'Toko laundry tidak ditemukan.', null, 404);
        }

        $courier = CourierProfile::with('user')->find($courierProfileId);
        if (!$courier) {
            return $this->errorResponse('COURIER_NOT_FOUND', 'Profil kurir tidak ditemukan.', null, 404);
        }

        // Cross-store protection
        if ($courier->store_id && $courier->store_id !== $store->id) {
            return $this->errorResponse(
                'UNAUTHORIZED_STORE_ACTION',
                'Anda tidak memiliki izin untuk menolak pelamar kurir dari toko lain.',
                null,
                403
            );
        }

        $reason = $request->input('reason', $request->input('rejection_reason'));

        $courier->verification_status = 'rejected';
        $courier->rejection_reason = $reason;
        $courier->status = 'inactive';
        $courier->save();

        $courierRole = Role::where('code', 'courier')->first();
        if ($courierRole) {
            UserRole::where('user_id', $courier->user_id)
                ->where('role_id', $courierRole->id)
                ->update(['status' => 'rejected']);
        }

        return $this->successResponse([
            'courier_profile_id' => $courier->id,
            'verification_status' => 'rejected',
            'rejection_reason' => $courier->rejection_reason,
        ], [
            'message' => "Pengajuan kurir {$courier->user?->name} telah ditolak.",
        ]);
    }
}
