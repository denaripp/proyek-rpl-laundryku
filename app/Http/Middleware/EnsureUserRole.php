<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * Validates that the authenticated user's database role matches
     * the role(s) required by the endpoint.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            abort(401, 'Unauthenticated.');
        }

        if (! $user->is_active) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Akun Anda dinonaktifkan.'], 403);
            }
            abort(403, 'Akun Anda dinonaktifkan.');
        }

        // Block provisional 2FA tokens from role-protected endpoints
        if ($user->currentAccessToken() && method_exists($user->currentAccessToken(), 'can') && $user->currentAccessToken()->can('2fa-pending')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Selesaikan verifikasi 2FA terlebih dahulu sebelum mengakses endpoint ini.',
                    'requires_2fa' => true,
                ], 403);
            }
            abort(403, 'Selesaikan verifikasi 2FA terlebih dahulu.');
        }

        // Direct real-time check against database users.role column or active user_roles table
        $dbRole = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->value('role');

        $hasRole = in_array($dbRole, $roles, true);

        if (! $hasRole) {
            $hasRole = \Illuminate\Support\Facades\DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $user->id)
                ->where('user_roles.status', 'active')
                ->whereIn('roles.name', $roles)
                ->exists();
        }

        if (! $hasRole) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Forbidden. Akun Anda tidak memiliki hak akses role yang sesuai.',
                ], 403);
            }
            abort(403, 'Forbidden. Akun Anda tidak memiliki hak akses role yang sesuai.');
        }

        return $next($request);
    }
}
