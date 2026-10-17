<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    use ApiResponse;

    /**
     * Dedicated Super Admin Login via Username + Password.
     * Rate limited via throttle:login (5 attempts/min per IP + username).
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                code: 'VALIDATION_ERROR',
                message: 'Validasi gagal: ' . $validator->errors()->first(),
                details: $validator->errors()->toArray(),
                status: 422
            );
        }

        $username = trim($request->input('username'));
        $password = $request->input('password');

        // Look up user by username (or email fallback)
        $user = User::where('username', $username)
            ->orWhere('email', $username)
            ->first();

        // 1. Check if user exists and password is correct (generic error message to prevent enumeration)
        if (!$user || empty($user->password) || !Hash::check($password, $user->password)) {
            // Record failed login audit log (actor null, username sanitized in metadata, never store password)
            AuditLog::record(
                actor: null,
                action: 'ADMIN_LOGIN_FAILED',
                entityType: 'admin_auth',
                entityId: $user?->id,
                metadata: [
                    'attempted_username' => $username,
                    'reason' => 'INVALID_CREDENTIALS',
                ],
                ip: $request->ip()
            );

            return $this->errorResponse(
                code: 'INVALID_CREDENTIALS',
                message: 'Kredensial login tidak valid. Silakan periksa kembali username dan password Anda.',
                status: 401
            );
        }

        // 2. Verify super_admin role
        if (!$user->hasRole('super_admin')) {
            AuditLog::record(
                actor: $user,
                action: 'ADMIN_LOGIN_REJECTED',
                entityType: 'admin_auth',
                entityId: $user->id,
                metadata: [
                    'username' => $user->username ?? $user->email,
                    'reason' => 'NOT_SUPER_ADMIN',
                ],
                ip: $request->ip()
            );

            return $this->errorResponse(
                code: 'FORBIDDEN',
                message: 'Akses ditolak. Akun Anda tidak memiliki hak akses sebagai Super Admin.',
                status: 403
            );
        }

        // 3. Check account status
        if (in_array($user->status, ['suspended', 'banned'])) {
            AuditLog::record(
                actor: $user,
                action: 'ADMIN_LOGIN_BLOCKED',
                entityType: 'admin_auth',
                entityId: $user->id,
                metadata: [
                    'username' => $user->username ?? $user->email,
                    'status' => $user->status,
                ],
                ip: $request->ip()
            );

            return $this->errorResponse(
                code: 'ACCOUNT_' . strtoupper($user->status),
                message: "Akun Anda sedang di-{$user->status}. Silakan hubungi administrator sistem.",
                status: 403
            );
        }

        // TODO: [2FA] Saat 2FA diimplementasikan, periksa apakah user telah mengaktifkan 2FA.
        // Jika aktif, kirimkan tantangan 2FA (TOTP/Email/WhatsApp) dan kembalikan response 2FA_REQUIRED
        // dengan temporary session token alih-alih langsung menerbitkan full personal access token.

        // Issue Sanctum token for authenticated Super Admin
        $token = $user->createToken('admin_auth_token')->plainTextToken;
        $user->loadMissing(['roles', 'profile']);

        // Record successful login audit log
        AuditLog::record(
            actor: $user,
            action: 'ADMIN_LOGIN_SUCCESS',
            entityType: 'admin_auth',
            entityId: $user->id,
            metadata: [
                'username' => $user->username,
                'email' => $user->email,
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'avatar_url' => $user->profile?->avatar_url,
                    'roles' => $user->roles->filter(fn ($r) => ($r->pivot->status ?? null) === 'active')->pluck('code')->values(),
                    'profile' => $user->profile,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            meta: [
                'message' => 'Login Super Admin berhasil. Selamat datang di Panel Kontrol LaundryKu.',
            ]
        );
    }

    /**
     * Logout Super Admin and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::record(
                actor: $user,
                action: 'ADMIN_LOGOUT_SUCCESS',
                entityType: 'admin_auth',
                entityId: $user->id,
                metadata: [
                    'username' => $user->username,
                ],
                ip: $request->ip()
            );

            $user->currentAccessToken()?->delete();
        }

        return $this->successResponse(
            data: null,
            meta: [
                'message' => 'Logout berhasil. Sesi admin telah diakhiri secara aman.',
            ]
        );
    }

    /**
     * Get current authenticated Super Admin session.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['roles', 'profile']);

        return $this->successResponse(
            data: [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'avatar_url' => $user->profile?->avatar_url,
                'roles' => $user->roles->filter(fn ($r) => ($r->pivot->status ?? null) === 'active')->pluck('code')->values(),
                'profile' => $user->profile,
            ]
        );
    }
}
