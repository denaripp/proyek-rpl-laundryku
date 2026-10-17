<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    use ApiResponse;

    /**
     * List users with role, status, and search filters.
     * FR-ADMIN-04, US-ADMIN-03
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'profile']);

        if ($request->filled('role')) {
            $roleCode = $request->query('role');
            $query->whereHas('roles', function ($rq) use ($roleCode) {
                $rq->where('code', $roleCode);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->query('q') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                  ->orWhere('email', 'LIKE', $search)
                  ->orWhere('phone', 'LIKE', $search);
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = collect($users->items())->map(fn ($u) => $this->formatUser($u));

        return $this->successResponse(
            data: $formatted,
            meta: [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        );
    }

    /**
     * Get user detail.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::with(['roles', 'profile', 'addresses', 'orders', 'stores'])->find($id);

        if (!$user) {
            return $this->errorResponse('NOT_FOUND', 'Pengguna tidak ditemukan.', null, 404);
        }

        return $this->successResponse(
            data: $this->formatUser($user, true)
        );
    }

    /**
     * Update user account status (active, suspended, banned).
     * Mandatory reason required when suspending or banning.
     * FR-ADMIN-05, US-ADMIN-03
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $admin = $request->user();
        $targetUser = User::find($id);

        if (!$targetUser) {
            return $this->errorResponse('NOT_FOUND', 'Pengguna tidak ditemukan.', null, 404);
        }

        // Prevent admin from banning themselves
        if ($targetUser->id === $admin->id) {
            return $this->errorResponse('FORBIDDEN', 'Anda tidak dapat mengubah status akun Anda sendiri.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:active,suspended,banned'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Status akun wajib dipilih.',
            'status.in' => 'Status hanya boleh: active, suspended, atau banned.',
        ]);

        $validator->sometimes('reason', 'required|min:5', function ($input) {
            return in_array($input->status, ['suspended', 'banned']);
        });

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Input tidak valid.', $validator->errors()->toArray(), 422);
        }

        $newStatus = $request->input('status');
        $reason = $request->input('reason');
        $oldStatus = $targetUser->status;

        $targetUser->status = $newStatus;
        $targetUser->status_reason = in_array($newStatus, ['suspended', 'banned']) ? $reason : null;
        $targetUser->save();

        // If user is suspended or banned, revoke all active sessions/tokens
        if (in_array($newStatus, ['suspended', 'banned'])) {
            $targetUser->tokens()->delete();
        }

        // Record Audit Log (FR-ADMIN-10, US-ADMIN-08)
        AuditLog::record(
            actor: $admin,
            action: 'UPDATE_USER_STATUS',
            entityType: 'users',
            entityId: $targetUser->id,
            metadata: [
                'user_name' => $targetUser->name,
                'user_email' => $targetUser->email,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: $this->formatUser($targetUser->fresh(['roles', 'profile'])),
            meta: ['message' => "Status pengguna '{$targetUser->name}' berhasil diperbarui menjadi '{$newStatus}'."]
        );
    }

    /**
     * Format user response.
     */
    protected function formatUser(User $user, bool $detailed = false): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'status_reason' => $user->status_reason,
            'roles' => $user->roles->pluck('code')->toArray(),
            'verified_at' => $user->verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['profile'] = $user->profile ? [
                'avatar_url' => $user->profile->avatar_url,
                'gender' => $user->profile->gender,
                'birth_date' => $user->profile->birth_date,
            ] : null;
        }

        return $data;
    }
}
