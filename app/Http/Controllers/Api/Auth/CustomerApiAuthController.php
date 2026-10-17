<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\CustomerRegisterRequest;
use App\Http\Requests\Api\Auth\LoginApiRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerApiAuthController extends Controller
{
    /**
     * Register a new customer via API.
     */
    public function register(CustomerRegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => User::ROLE_CUSTOMER,
            'phone' => $request->validated('phone'),
            'password' => Hash::make($request->validated('password')),
            'is_active' => true,
        ]);

        // Revoke any existing tokens for safety
        $user->tokens()->where('name', 'customer_token')->delete();

        // Issue token with role:customer ability
        $token = $user->createToken('customer_token', ['role:customer'])->plainTextToken;

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => User::ROLE_CUSTOMER,
            'action' => 'CUSTOMER_API_REGISTER',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Registrasi customer berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
            ],
        ], 201);
    }

    /**
     * Authenticate customer and issue Sanctum token.
     */
    public function login(LoginApiRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        // Strict role isolation: User must exist, match customer role, and password must be valid.
        // Return identical generic error on any failure to prevent enumeration.
        if (! $user || $user->role !== User::ROLE_CUSTOMER || ! Hash::check($request->input('password'), $user->password)) {
            AuditLog::create([
                'actor_id' => $user?->id,
                'actor_role' => User::ROLE_CUSTOMER,
                'action' => 'CUSTOMER_API_LOGIN_FAILED',
                'resource_type' => 'auth',
                'resource_id' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'after_value' => json_encode(['email' => $request->input('email')]),
            ]);

            return response()->json([
                'message' => __('auth.failed'),
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun Anda dinonaktifkan.',
            ], 403);
        }

        // Revoke old customer tokens on new login (one active token per user role)
        $user->tokens()->where('name', 'customer_token')->delete();

        $token = $user->createToken('customer_token', ['role:customer'])->plainTextToken;
        $user->update(['last_login_at' => now()]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => User::ROLE_CUSTOMER,
            'action' => 'CUSTOMER_API_LOGIN_SUCCESS',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
            ],
        ], 200);
    }

    /**
     * Logout and revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Berhasil logout.',
        ], 200);
    }
}
