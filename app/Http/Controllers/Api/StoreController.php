<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\SearchStoresRequest;
use App\Models\Store;
use App\Services\Store\StoreSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tahap 16 — Store browsing endpoints (public + role-filtered).
 * Controller tipis: hanya validasi + policy + delegasi ke service.
 */
class StoreController extends Controller
{
    public function __construct(protected StoreSearchService $searchService) {}

    /**
     * GET /api/stores — marketplace search.
     */
    public function index(SearchStoresRequest $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $results = $this->searchService->search(
            $request->only(['q', 'service_id', 'min_rating', 'latitude', 'longitude', 'radius_km', 'sort']),
            page: (int) $request->input('page', 1),
            perPage: $perPage
        );

        return response()->json($results);
    }

    /**
     * GET /api/stores/{store} — store detail.
     */
    public function show(Request $request, Store $store)
    {
        Gate::forUser($request->user())->authorize('view', $store);

        $store->load([
            'servicePrices.service',
            'staff.user:id,name,email',
        ]);

        return response()->json($store);
    }

    /**
     * GET /api/stores/{store}/services — service prices.
     */
    public function services(Request $request, Store $store)
    {
        Gate::forUser($request->user())->authorize('view', $store);

        return response()->json(
            $store->servicePrices()->where('is_active', true)->with('service')->get()
        );
    }
}
