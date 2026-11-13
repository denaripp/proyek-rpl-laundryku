<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // Inactive user check
            if (! $user->is_active) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['message' => 'Akun Anda dinonaktifkan.'], 403);
                }
                abort(403, 'Akun Anda dinonaktifkan.');
            }

            // 1. API / Sanctum token check:
            // Provisional 2FA tokens can ONLY access 2FA verification endpoints.
            $token = $user->currentAccessToken();
            if ($token && !($token instanceof \Laravel\Sanctum\TransientToken) && (in_array('2fa-pending', $token->abilities ?? [], true) || in_array('auth:2fa-pending', $token->abilities ?? [], true))) {
                $is2faApiRoute = $request->is('api/v1/auth/mitra/2fa')
                    || $request->is('api/v1/auth/admin/2fa')
                    || $request->routeIs('*.2fa')
                    || $request->routeIs('2fa.*');

                if (! $is2faApiRoute) {
                    return response()->json([
                        'message' => 'Selesaikan verifikasi 2FA terlebih dahulu sebelum mengakses endpoint ini.',
                        'requires_2fa' => true,
                    ], 403);
                }
            }

            // 2. Web session check:
            if ($user->two_factor_enabled && $request->hasSession() && $request->session()->has('pending_2fa')) {
                // Allow only 2FA verification and logout routes (prevent 2fa.disable / 2fa.enable bypass)
                $allowedRoutes = ['2fa.verify', '2fa.verify.post', 'logout', 'mitra.logout', 'kurir.logout', 'admin.logout', 'admin.2fa', 'admin.2fa.verify'];
                $currentRouteName = $request->route()?->getName();

                if (! in_array($currentRouteName, $allowedRoutes, true)) {
                    if ($request->is('admin*')) {
                        return redirect()->route('admin.2fa');
                    }
                    return redirect()->route('2fa.verify');
                }
            }
        }

        return $next($request);
    }
}
