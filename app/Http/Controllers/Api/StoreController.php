<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\SearchStoresRequest;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Services\Store\StoreSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Tahap 16 — Store browsing endpoints (public + role-filtered).
 * Controller tipis: validasi + policy + delegasi ke service.
 */
class StoreController extends Controller
{
    public function __construct(protected StoreSearchService $searchService) {}

    /**
     * Format a store model into the canonical contract expected by both Next.js frontend and legacy tests.
     */
    public function formatStore(Store $store, ?float $lat = null, ?float $lng = null): array
    {
        $shortAddress = $store->kecamatan ? "{$store->kecamatan}, {$store->city}" : ($store->city ?? $store->address);

        $services = $store->servicePrices->filter(fn($sp) => $sp->is_active !== false)->map(function ($sp) {
            return [
                'id' => (string) $sp->id,
                'store_id' => (string) $sp->store_id,
                'category_id' => (string) $sp->service_id,
                'category_name' => $sp->service?->name,
                'category_slug' => Str::slug($sp->service?->name ?? ''),
                'name' => $sp->service?->name ?? 'Layanan',
                'description' => $sp->service?->description,
                'pricing_unit' => $sp->pricing_unit ?? 'kg',
                'price_per_unit' => (float) $sp->price_per_unit,
                'minimum_charge' => (float) ($sp->minimum_charge ?? 0),
                'estimated_duration_hours' => (int) ($sp->estimated_duration_hours ?? 24),
                'is_active' => (bool) $sp->is_active,
            ];
        })->values();

        $categories = $services->map(function ($s) {
            return [
                'id' => $s['category_id'],
                'name' => $s['category_name'] ?? 'Layanan',
                'slug' => $s['category_slug'] ?? 'layanan',
            ];
        })->unique('id')->values();

        $topServices = $services->take(3)->pluck('name')->toArray();
        $minPrice = $services->min('price_per_unit');

        $distance = isset($store->distance) ? (float) $store->distance : null;
        if ($distance === null && $lat !== null && $lng !== null && $store->latitude && $store->longitude) {
            $theta = $lng - (float) $store->longitude;
            $dist = sin(deg2rad($lat)) * sin(deg2rad((float) $store->latitude)) +
                    cos(deg2rad($lat)) * cos(deg2rad((float) $store->latitude)) * cos(deg2rad($theta));
            $dist = acos(min(max($dist, -1.0), 1.0));
            $dist = rad2deg($dist);
            $distance = round($dist * 60 * 1.1515 * 1.609344, 2);
        }

        $serviceRadius = (float) ($store->service_radius_km ?? 10);
        $isServiceable = $distance !== null ? ($distance <= $serviceRadius) : true;

        return [
            'id' => $store->id,
            'owner_user_id' => $store->owner_user_id,
            'name' => $store->name,
            'slug' => $store->slug ?? Str::slug($store->name),
            'description' => $store->description,
            'phone' => $store->phone,
            'address' => $store->address,
            'address_text' => $store->address,
            'short_address' => $shortAddress,
            'alamat_singkat' => $shortAddress,
            'city' => $store->city,
            'province' => $store->province,
            'postal_code' => $store->postal_code,
            'latitude' => $store->latitude !== null ? (float) $store->latitude : null,
            'longitude' => $store->longitude !== null ? (float) $store->longitude : null,
            'service_radius_km' => $serviceRadius,
            'minimum_order_amount' => $store->minimum_order_amount !== null ? (float) $store->minimum_order_amount : 0,
            'pickup_fee' => (float) ($store->pickup_fee ?? 0),
            'delivery_fee' => (float) ($store->delivery_fee ?? 0),
            'cod_enabled' => (bool) $store->cod_enabled,
            'opening_hours' => $store->operational_hours ?? ['all_day' => '08:00 - 20:00'],
            'opening_hours_today' => ['open' => '08:00', 'close' => '20:00', 'raw' => '08:00 - 20:00'],
            'jam_buka_tutup_hari_ini' => '08:00 - 20:00',
            'is_open_now' => true,
            'status_buka_sekarang' => true,
            'approval_status' => $store->status ?? 'approved',
            'operational_status' => $store->is_active ? 'active' : 'inactive',
            'status' => $store->status,
            'is_active' => (bool) $store->is_active,
            'logo' => $store->logo,
            'logo_url' => $store->logo,
            'banner' => $store->banner,
            'categories' => $categories,
            'services' => $services,
            'top_services' => $topServices,
            'tiga_layanan_teratas' => $topServices,
            'services_count' => $services->count(),
            'active_services_count' => $services->count(),
            'starting_price' => $minPrice !== null ? (float) $minPrice : 8000,
            'harga_mulai_dari' => $minPrice !== null ? (float) $minPrice : 8000,
            'starting_unit' => 'kg',
            'rating_average' => $store->average_rating !== null ? (float) $store->average_rating : 4.8,
            'rating' => $store->average_rating !== null ? (float) $store->average_rating : 4.8,
            'rating_count' => (int) ($store->review_count ?? 0),
            'reviews_count' => (int) ($store->review_count ?? 0),
            'distance_km' => $distance,
            'jarak' => $distance,
            'is_serviceable' => $isServiceable,
            'created_at' => $store->created_at?->toISOString() ?? now()->toISOString(),
            'updated_at' => $store->updated_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * GET /api/v1/stores & /api/stores — marketplace search.
     */
    public function index(SearchStoresRequest $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $results = $this->searchService->search(
            $request->only(['q', 'service_id', 'min_rating', 'latitude', 'longitude', 'radius_km', 'sort']),
            page: (int) $request->input('page', 1),
            perPage: $perPage
        );

        $lat = $request->input('latitude') ?? $request->input('lat');
        $lng = $request->input('longitude') ?? $request->input('lng');
        $radius = $request->input('radius_km');

        $formattedStores = collect($results->items())->map(function ($store) use ($lat, $lng) {
            return $this->formatStore($store, $lat !== null ? (float) $lat : null, $lng !== null ? (float) $lng : null);
        })->values();

        $serviceableTotal = $formattedStores->where('is_serviceable', true)->count();

        return response()->json([
            'success' => true,
            'data' => $formattedStores,
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'customer_lat' => $lat !== null ? (float) $lat : null,
                'customer_lng' => $lng !== null ? (float) $lng : null,
                'search_radius_km' => $radius !== null ? (float) $radius : 10,
                'serviceable_total' => $serviceableTotal,
            ],
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
            'total' => $results->total(),
        ]);
    }

    /**
     * GET /api/v1/stores/{store} — store detail.
     */
    public function show(Request $request, Store $store)
    {
        Gate::forUser($request->user())->authorize('view', $store);

        $store->load([
            'servicePrices.service',
            // staff excluded from public profile
            'reviews' => function ($q) {
                $q->visible()->with('customer:id,name')->latest()->take(10);
            },
        ]);

        $lat = $request->input('lat') !== null ? (float) $request->input('lat') : null;
        $lng = $request->input('lng') !== null ? (float) $request->input('lng') : null;

        $formatted = $this->formatStore($store, $lat, $lng);

        return response()->json(array_merge($formatted, [
            'success' => true,
            'data' => $formatted,
        ]));
    }

    /**
     * GET /api/v1/stores/{store}/services — service prices.
     */
    public function services(Request $request, Store $store)
    {
        Gate::forUser($request->user())->authorize('view', $store);

        $services = $store->servicePrices()
            ->where('is_active', true)
            ->with('service')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $services,
            'services' => $services,
        ]);
    }

    /**
     * GET /api/v1/stores/{store}/ratings — ratings and review breakdown.
     */
    public function ratings(Request $request, Store $store)
    {
        $reviewsQuery = $store->reviews()
            ->visible()
            ->with(['customer:id,name', 'order:id,order_number']);

        if ($request->filled('score')) {
            $reviewsQuery->where('rating', (int) $request->input('score'));
        }

        if ($request->boolean('has_review')) {
            $reviewsQuery->whereNotNull('comment')->where('comment', '!=', '');
        }

        $paginated = $reviewsQuery->latest()->paginate((int) $request->input('per_page', 10));

        $allReviews = $store->reviews()->visible()->get();
        $totalRatings = $allReviews->count();
        $avgScore = $totalRatings > 0 ? round($allReviews->avg('rating'), 1) : 0;

        $breakdown = [
            '5' => $allReviews->where('rating', 5)->count(),
            '4' => $allReviews->where('rating', 4)->count(),
            '3' => $allReviews->where('rating', 3)->count(),
            '2' => $allReviews->where('rating', 2)->count(),
            '1' => $allReviews->where('rating', 1)->count(),
        ];

        $items = collect($paginated->items())->map(function ($r) use ($store) {
            return [
                'id' => (string) $r->id,
                'order_id' => (string) ($r->order_id ?? ''),
                'order_number' => $r->order?->order_number ?? 'ORD-' . $r->id,
                'customer_id' => (string) ($r->customer_user_id ?? $r->customer_id ?? ''),
                'customer_name' => $r->customer?->name ?? 'Pelanggan',
                'customer_avatar' => null,
                'store_id' => (string) $store->id,
                'store_name' => $store->name,
                'score' => (int) $r->rating,
                'review' => $r->comment,
                'created_at' => $r->created_at?->toISOString() ?? now()->toISOString(),
                'updated_at' => $r->updated_at?->toISOString() ?? now()->toISOString(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'summary' => [
                    'average_score' => (float) $avgScore,
                    'total_ratings' => $totalRatings,
                    'breakdown' => $breakdown,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/stores/lookup — fast store lookup by name.
     */
    public function lookup(Request $request)
    {
        $q = $request->input('q', '');
        $stores = Store::approved()
            ->when($q, fn($query) => $query->where('name', 'LIKE', "%{$q}%"))
            ->limit(10)
            ->get()
            ->map(function ($s) {
                return [
                    'id' => (string) $s->id,
                    'name' => $s->name,
                    'address_text' => $s->address,
                    'logo_url' => $s->logo,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $stores,
        ]);
    }

    /**
     * GET /api/v1/service-categories — list of active services.
     */
    public function categories()
    {
        $services = Service::where('is_active', true)->get()->map(function ($s) {
            return [
                'id' => (string) $s->id,
                'name' => $s->name,
                'slug' => Str::slug($s->name),
                'status' => 'active',
                'description' => $s->description,
                'icon' => $s->icon,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /**
     * POST /api/v1/pricing/estimate — price estimation for checkout.
     */
    public function estimatePrice(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $storeId = $validated['store_id'];
        $store = Store::where('id', $storeId)->where('status', 'approved')->where('is_active', true)->first();
        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan atau belum aktif.',
            ], 404);
        }

        $items = $validated['items'];

        $estimatedItems = [];
        $totalRaw = 0;
        $totalEstimated = 0;
        $maxDuration = 24;

        foreach ($items as $item) {
            $serviceId = $item['service_id'] ?? null;
            $qty = (float) ($item['quantity'] ?? 1);
            $sp = ServicePrice::where('store_id', $storeId)
                ->where('service_id', $serviceId)
                ->with('service')
                ->first();

            $pricePerUnit = $sp ? (float) $sp->price_per_unit : 10000;
            $minCharge = $sp ? (float) ($sp->minimum_charge ?? 0) : 0;
            $subtotal = $pricePerUnit * $qty;
            $charged = max($subtotal, $minCharge);
            $minApplied = $charged > $subtotal;

            $totalRaw += $subtotal;
            $totalEstimated += $charged;

            $estimatedItems[] = [
                'service_id' => (string) $serviceId,
                'service_name' => $sp?->service?->name ?? 'Layanan',
                'category_name' => $sp?->service?->name ?? 'Layanan',
                'pricing_unit' => $sp?->pricing_unit ?? 'kg',
                'price_per_unit' => $pricePerUnit,
                'quantity' => $qty,
                'subtotal' => $subtotal,
                'minimum_charge' => $minCharge,
                'minimum_charge_applied' => $minApplied,
                'charged_amount' => $charged,
                'estimated_duration_hours' => 24,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'store' => [
                    'id' => (string) ($store?->id ?? $storeId),
                    'name' => $store?->name ?? 'Toko',
                    'service_radius_km' => (float) ($store?->service_radius_km ?? 10),
                ],
                'items' => $estimatedItems,
                'summary' => [
                    'total_items' => count($estimatedItems),
                    'total_raw_subtotal' => $totalRaw,
                    'total_estimated_price' => $totalEstimated,
                    'max_estimated_duration_hours' => $maxDuration,
                    'currency' => 'IDR',
                    'notes' => 'Estimasi biaya awal',
                ],
            ],
        ]);
    }
}
