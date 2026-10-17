<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    use ApiResponse;

    /**
     * List audit logs with pagination and filters.
     * FR-ADMIN-10, US-ADMIN-08
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('actor');

        if ($request->filled('action')) {
            $query->where('action', strtoupper($request->query('action')));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->query('actor_id'));
        }

        if ($request->filled('q')) {
            $search = '%' . $request->query('q') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'LIKE', $search)
                  ->orWhere('entity_type', 'LIKE', $search)
                  ->orWhere('entity_id', 'LIKE', $search)
                  ->orWhereHas('actor', fn ($aq) => $aq->where('name', 'LIKE', $search));
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = collect($logs->items())->map(fn ($log) => [
            'id' => $log->id,
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->name,
                'email' => $log->actor->email,
            ] : null,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'metadata' => $log->metadata,
            'ip_hash' => $log->ip_hash,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return $this->successResponse(
            data: $formatted,
            meta: [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        );
    }

    /**
     * Show audit log detail.
     */
    public function show(string $id): JsonResponse
    {
        $log = AuditLog::with('actor')->find($id);

        if (!$log) {
            return $this->errorResponse('NOT_FOUND', 'Audit log tidak ditemukan.', null, 404);
        }

        return $this->successResponse(
            data: [
                'id' => $log->id,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ] : null,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'metadata' => $log->metadata,
                'ip_hash' => $log->ip_hash,
                'created_at' => $log->created_at?->toIso8601String(),
            ]
        );
    }
}
