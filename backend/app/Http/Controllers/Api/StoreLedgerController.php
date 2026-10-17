<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Services\LedgerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreLedgerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Store Admin views their store ledger entries and financial summary (FR-PAY-11, US-STORE-08).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Super Admin can pass store_id query, otherwise Store Admin gets their own store
        $storeId = $request->query('store_id');
        $store = null;

        if ($user->hasRole('super_admin') && $storeId) {
            $store = Store::find($storeId);
        } else {
            $store = Store::where('owner_user_id', $user->id)->first();
        }

        if (!$store) {
            return $this->errorResponse('NOT_FOUND', 'Toko tidak ditemukan atau akun ini bukan admin toko.', null, 404);
        }

        // Fetch summary
        $summary = $this->ledgerService->getStoreSummary($store);

        // Fetch entries with filter & pagination
        $query = StoreLedgerEntry::where('store_id', $store->id)
            ->with(['order', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('entry_type')) {
            $query->where('entry_type', strtoupper($request->query('entry_type')));
        }

        if ($request->filled('direction')) {
            $query->where('direction', strtolower($request->query('direction')));
        }

        $perPage = (int) $request->query('per_page', 20);
        $paginated = $query->paginate($perPage);

        $entries = collect($paginated->items())->map(function ($entry) {
            return [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'direction' => $entry->direction,
                'amount' => $entry->amount,
                'description' => $entry->description,
                'order_id' => $entry->order_id,
                'order_number' => $entry->order?->order_number,
                'created_by_name' => $entry->creator?->name,
                'created_at' => $entry->created_at?->toIso8601String(),
            ];
        });

        return $this->successResponse(
            data: [
                'summary' => $summary,
                'entries' => $entries,
            ],
            meta: [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ]
        );
    }
}
