<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return standardized success JSON response per PRD 12.1.
     *
     * @param mixed $data
     * @param array $meta
     * @param int $status
     * @return JsonResponse
     */
    public function successResponse(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data ?? (object)[],
            'meta' => (object)$meta,
        ], $status);
    }

    /**
     * Return standardized error JSON response per PRD 12.1.
     *
     * @param string $code
     * @param string $message
     * @param mixed $details
     * @param int $status
     * @return JsonResponse
     */
    public function errorResponse(string $code, string $message, mixed $details = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details ?? (object)[],
            ],
        ], $status);
    }
}
