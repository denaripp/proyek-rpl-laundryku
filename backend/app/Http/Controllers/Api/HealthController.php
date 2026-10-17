<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    use ApiResponse;

    /**
     * Check system and database health conforming to PRD 12.1.
     */
    public function index(): JsonResponse
    {
        $dbConnected = false;
        $dbMessage = null;

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $dbConnected = true;
        } catch (Throwable $e) {
            $dbMessage = $e->getMessage();
        }

        $isLocal = app()->environment('local');

        $data = [
            'status' => $dbConnected ? 'ok' : 'degraded',
            'app_name' => config('app.name', 'LaundryKu'),
            'service' => config('app.name', 'LaundryKu') . ' API',
            'api_version' => 'v1',
            'server_time' => now()->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
            'database' => $dbConnected ? 'connected' : 'disconnected',
            'database_driver' => config('database.default'),
        ];

        if ($isLocal) {
            $data['laravel_version'] = app()->version();
            $data['db_error'] = $dbMessage;
        }

        if (!$dbConnected) {
            return $this->errorResponse(
                code: 'DATABASE_DISCONNECTED',
                message: 'Database service is unavailable.',
                details: $data,
                status: 503
            );
        }

        return $this->successResponse($data);
    }
}
