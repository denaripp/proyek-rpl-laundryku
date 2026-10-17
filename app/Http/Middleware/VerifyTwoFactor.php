<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // If user has 2FA enabled and is not verified yet
        if ($user && $user->two_factor_enabled && $request->session()->has('pending_2fa')) {
            // Allow only 2FA routes
            if (!$request->routeIs('2fa.*') && !$request->routeIs('logout')) {
                return redirect()->route('2fa.verify');
            }
        }

        return $next($request);
    }
}