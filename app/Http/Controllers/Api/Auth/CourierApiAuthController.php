<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginApiRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CourierApiAuthController extends Controller
{
    /**
     * Authenticate courier and issue Sanctum token.
     */
    public function login(LoginApiRequest $request): JsonResponse
    {
        $identifier = strtolower(trim((string) $request->input('email')));
        $cleanPhone = preg_replace('/[^0-9]/', '', $identifier);

        $user = User::where(function ($query) use ($identifier, $cleanPhone) {
            $query->whereRaw('LOWER(TRIM(email)) = ?', [$identifier]);
            if (!empty($cleanPhone)) {
                $variants = [$cleanPhone];
                if (str_starts_with($cleanPhone, '62')) {
                    $variants[] = '0' . substr($cleanPhone, 2);
                    $variants[] = '+' . $cleanPhone;
                } elseif (str_starts_with($cleanPhone, '0')) {
                    $variants[] = '62' . substr($cleanPhone, 1);
                    $variants[] = '+62' . substr($cleanPhone, 1);
                }
                $query->orWhereIn('phone', $variants);
            }
        })->first();

        $isCourier = $user && (
            $user->role === User::ROLE_COURIER ||
            (method_exists($user, 'hasRole') && $user->hasRole('courier')) ||
            $user->roles()->where('name', 'courier')->exists() ||
            $user->courier()->exists()
        );

        if (! $user || ! $isCourier || ! Hash::check($request->input('password'), $user->password)) {
            AuditLog::create([
                'actor_id' => $user?->id,
                'actor_role' => User::ROLE_COURIER,
                'action' => 'COURIER_API_LOGIN_FAILED',
                'resource_type' => 'auth',
                'resource_id' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'after_value' => json_encode(['identifier' => $identifier]),
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

        // Sync role to courier if necessary for session consistency
        if ($user->role !== User::ROLE_COURIER) {
            $user->role = User::ROLE_COURIER;
            $user->save();
        }

        // Revoke old courier tokens
        $user->tokens()->where('name', 'courier_token')->delete();

        $token = $user->createToken('courier_token', ['role:courier'])->plainTextToken;
        $user->update(['last_login_at' => now()]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => User::ROLE_COURIER,
            'action' => 'COURIER_API_LOGIN_SUCCESS',
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
     * Logout courier and revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Berhasil logout.',
        ], 200);
    }
}
