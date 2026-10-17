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

        // Direct real-time check against database users.role column
        $dbRole = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->value('role');

        if (! in_array($dbRole, $roles, true)) {
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
