<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tahap 16 — Store owner / staff management endpoints.
 */
class StoreOwnerController extends Controller
{
    public function __construct(protected StoreService $storeService) {}

    /**
     * GET /api/store-owner/stores — list stores owned/staffed by user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Store::class);

        $owned = $user->ownedStores()->get();
        $staffed = $user->storeStaff()->with('store')->where('is_active', true)->get()->pluck('store');

        return response()->json([
            'owned' => $owned,
            'staffed' => $staffed,
        ]);
    }

    /**
     * GET /api/store-owner/stores/{store}
     */
    public function show(Request $request, Store $store)
    {
        // SECURITY (Tahap 20): viewOperations — staff list + pricing details
        // are sensitive to store-internal exposure.
        Gate::forUser($request->user())->authorize('viewOperations', $store);

        return response()->json($store->load(['servicePrices.service', 'staff.user:id,name,email']));
    }

    /**
     * PATCH /api/store-owner/stores/{store}
     */
    public function update(UpdateStoreRequest $request, Store $store)
    {
        Gate::forUser($request->user())->authorize('update', $store);

        $store->fill($request->validated());
        $store->save();

        return response()->json($store);
    }

    /**
     * POST /api/store-owner/stores/{store}/submit — submit for review.
     */
    public function submit(Request $request, Store $store)
    {
        Gate::forUser($request->user())->authorize('update', $store);

        $updated = $this->storeService->submit($store);
        return response()->json($updated);
    }

    /**
     * GET /api/store-owner/stores/{store}/orders — orders for this store.
     */
    public function orders(Request $request, Store $store)
    {
        // SECURITY (Tahap 20): viewOperations (strict ownership).
        Gate::forUser($request->user())->authorize('viewOperations', $store);

        $query = $store->orders()->with('customer:id,name', 'items');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latest()->paginate(15));
    }
}
