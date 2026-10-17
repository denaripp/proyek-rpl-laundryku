<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredMode): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Sesi tidak valid atau telah berakhir. Silakan login kembali.',
                    'details' => (object)[],
                ],
            ], 401);
        }

        if ($requiredMode === 'courier') {
            $hasActiveAssignment = $user->hasActiveCourierAssignment();

            if (!$hasActiveAssignment) {
                // If user was previously in courier mode, downgrade to customer with MODE_REVOKED
                if ($user->active_mode === 'courier') {
                    $user->active_mode = 'customer';
                    $user->save();

                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'MODE_REVOKED',
                            'message' => 'Penugasan kurir Anda tidak aktif. Mode telah dialihkan ke pelanggan.',
                            'details' => (object)[],
                        ],
                    ], 403);
                }

                // If user never had an active assignment, return standard NO_ACTIVE_STORE
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NO_ACTIVE_STORE',
                        'message' => 'Anda belum terikat secara aktif pada toko laundry manapun.',
                        'details' => (object)[],
                    ],
                ], 403);
            }

            // D.1: Default mode after login: courier if active assignment exists and user has no approved store
            if ($user->active_mode === null && !$user->hasApprovedStore()) {
                $user->active_mode = 'courier';
                $user->save();
            }

            if ($user->active_mode !== 'courier') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Akses ditolak. Anda harus beralih ke mode kurir terlebih dahulu.',
                        'details' => (object)[],
                    ],
                ], 403);
            }
        } elseif ($requiredMode === 'store') {
            $hasStore = $user->hasApprovedStore() || $user->hasRole('store_admin');
            if (!$hasStore) {
                if ($user->active_mode !== 'customer') {
                    $user->active_mode = 'customer';
                    $user->save();
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'MODE_REVOKED',
                        'message' => 'Toko Anda belum disetujui atau dinonaktifkan. Mode telah dialihkan ke pelanggan.',
                        'details' => (object)[],
                    ],
                ], 403);
            }

            if ($user->active_mode !== 'store') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Akses ditolak. Anda harus beralih ke mode toko terlebih dahulu.',
                        'details' => (object)[],
                    ],
                ], 403);
            }
        }

        return $next($request);
    }
}
