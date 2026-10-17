<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Akses ditolak. Tindakan ini hanya dapat dilakukan oleh Super Admin.',
                    'details' => (object)[],
                ],
            ], 403);
        }

        return $next($request);
    }
}
