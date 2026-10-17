<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensure user is authenticated and possesses platform_admin or platform_owner role
     * (or view_admin_dashboard permission).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $isAdminOrOwner = $user->hasRole('platform_admin')
            || $user->hasRole('platform_owner')
            || $user->hasPermissionTo('view_admin_dashboard')
            || $user->hasPermissionTo('manage_platform');

        if (!$isAdminOrOwner) {
            abort(403, 'Unauthorized. Akses ini hanya untuk Platform Admin atau Platform Owner.');
        }

        return $next($request);
    }
}
