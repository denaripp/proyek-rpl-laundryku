<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    use ApiResponse;

    /**
     * Fast lookup endpoint for combobox/searchable store selection (FR-MKT, US-COU-01).
     * GET /api/v1/stores/lookup?q=
     */
    public function lookup(Request $request): JsonResponse
    {
        $rawQ = (string) $request->query('q', '');
        $q = trim(preg_replace('/\s+/', ' ', $rawQ));

        if (mb_strlen($q) < 2) {
            return $this->successResponse(
                data: [],
                meta: [
                    'message' => 'Ketik minimal 2 karakter untuk mencari toko.',
                    'total' => 0,
                ]
            );
        }

        if (mb_strlen($q) > 100) {
            $q = mb_substr($q, 0, 100);
        }

        $stores = Store::query()
            ->where('approval_status', 'approved')
            ->where('operational_status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('address_text', 'LIKE', "%{$q}%");
            })
            ->select(['id', 'name', 'address_text'])
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get();

        $items = $stores->map(fn (Store $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'address_text' => $s->address_text,
        ]);

        return $this->successResponse(
            data: $items,
            meta: [
                'total' => $items->count(),
            ]
        );
    }

    /**
     * List approved & active stores with filtering and pagination (FR-MKT-01, FR-MKT-08).
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'uuid'],
            'category_slug' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Parameter filter toko tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $hasCoords = $request->filled('lat') && $request->filled('lng');
        $clientLat = $hasCoords ? (float) $request->input('lat') : null;
        $clientLng = $hasCoords ? (float) $request->input('lng') : null;

        $query = Store::query()
            ->where('approval_status', 'approved')
            ->where('operational_status', 'active')
            ->select([
                'stores.id',
                'stores.name',
                'stores.description',
                'stores.address_text',
                'stores.latitude',
                'stores.longitude',
                'stores.service_radius_km',
                'stores.opening_hours',
                'stores.approval_status',
                'stores.operational_status',
                'stores.created_at',
            ])
            ->withMin(['activeServices as starting_price' => fn ($q) => $q->where('is_active', true)], 'price_per_unit')
            ->withCount(['activeServices as active_services_count' => fn ($q) => $q->where('is_active', true)])
            ->withAvg('ratings', 'score')
            ->withCount('ratings')
            ->with([
                'activeServices' => function ($q) {
                    $q->where('is_active', true)
                        ->select(['id', 'store_id', 'category_id', 'name', 'price_per_unit', 'pricing_unit', 'minimum_charge', 'estimated_duration_hours', 'is_active'])
                        ->with('category:id,name,slug')
                        ->orderBy('price_per_unit', 'asc');
                },
            ]);

        // Calculate Haversine distance if lat/lng are provided by client
        if ($hasCoords) {
            $haversineSql = '
                (6371.0 * acos(
                    CASE
                        WHEN (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))) > 1.0 THEN 1.0
                        WHEN (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))) < -1.0 THEN -1.0
                        ELSE (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))
                    END
                ))
            ';
            $bindings = [$clientLat, $clientLng, $clientLat, $clientLat, $clientLng, $clientLat, $clientLat, $clientLng, $clientLat];
            $query->selectRaw("{$haversineSql} AS distance_km", $bindings);
        }

        // Search by keyword (name, description, address_text)
        $search = $request->input('search', $request->input('q'));
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('address_text', 'LIKE', "%{$search}%");
            });
        }

        // Filter by service category id or slug
        $categoryId = $request->input('category_id');
        $categorySlug = $request->input('category_slug');

        if (!empty($categoryId)) {
            $query->whereHas('activeServices', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        } elseif (!empty($categorySlug)) {
            $query->whereHas('activeServices.category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        if ($hasCoords) {
            $query->orderByRaw("{$haversineSql} ASC", $bindings);
        } else {
            $query->orderBy('name', 'asc');
        }

        $perPage = (int) $request->input('per_page', 12);
        $stores = $query->paginate($perPage);

        $items = collect($stores->items())->map(function (Store $store) use ($hasCoords, $clientLat, $clientLng) {
            return $this->formatStore($store, false, $hasCoords ? $clientLat : null, $hasCoords ? $clientLng : null);
        });

        return $this->successResponse(
            data: $items,
            meta: [
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
                'per_page' => $stores->perPage(),
                'total' => $stores->total(),
            ]
        );
    }

    /**
     * Get store detail, active services, and operational hours (FR-MKT-08, US-CUS-04).
     * Non-approved or inactive stores return 404.
     */
    public function show(string $id): JsonResponse
    {
        $store = Store::where('id', $id)
            ->where('approval_status', 'approved')
            ->where('operational_status', 'active')
            ->with(['activeServices.category'])
            ->withAvg('ratings', 'score')
            ->withCount('ratings')
            ->first();


        if (!$store) {
            return $this->errorResponse(
                'NOT_FOUND',
                'Toko tidak ditemukan atau sedang tidak aktif.',
                null,
                404
            );
        }

        return $this->successResponse(
            data: $this->formatStore($store, true)
        );
    }

    /**
     * Find nearby stores using PostgreSQL Haversine formula (FR-MKT-02, FR-MKT-04, FR-MKT-05, FR-MKT-06).
     * Validates coordinates and radius limit (max 10km), marks is_serviceable.
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:10.0'],
            'search' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'uuid'],
            'category_slug' => ['nullable', 'string', 'max:100'],
        ], [
            'lat.required' => 'Koordinat latitude customer wajib diisi.',
            'lat.between' => 'Nilai latitude harus berada di antara -90 dan 90 derajat.',
            'lng.required' => 'Koordinat longitude customer wajib diisi.',
            'lng.between' => 'Nilai longitude harus berada di antara -180 dan 180 derajat.',
            'radius_km.max' => 'Jarak radius pencarian maksimal adalah 10 km.',
            'radius_km.min' => 'Jarak radius pencarian minimal adalah 0.1 km.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Parameter lokasi pencarian toko tidak valid.',
                $validator->errors()->toArray(),
                422
            );
        }

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $radiusKm = min((float) $request->input('radius_km', 10.0), 10.0);

        /**
         * PostgreSQL Haversine Distance Formula:
         * 6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(rad(?)) * cos(rad(latitude)) * cos(rad(longitude) - rad(?)) + sin(rad(?)) * sin(rad(latitude)))))
         * Clamping value between -1.0 and 1.0 prevents NaN / acos error at identical coordinates (distance = 0).
         */
        $haversineSql = '
            (6371.0 * acos(
                CASE
                    WHEN (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))) > 1.0 THEN 1.0
                    WHEN (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))) < -1.0 THEN -1.0
                    ELSE (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))
                END
            ))
        ';

        $query = Store::query()
            ->select('stores.*')
            ->selectRaw("{$haversineSql} AS distance_km", [$lat, $lng, $lat, $lat, $lng, $lat, $lat, $lng, $lat])
            ->where('approval_status', 'approved')
            ->where('operational_status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['activeServices.category'])
            ->withAvg('ratings', 'score')
            ->withCount('ratings');

        // Search keyword filter
        $search = $request->input('search', $request->input('q'));
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('address_text', 'LIKE', "%{$search}%");
            });
        }

        // Category filter
        $categoryId = $request->input('category_id');
        $categorySlug = $request->input('category_slug');

        if (!empty($categoryId)) {
            $query->whereHas('activeServices', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        } elseif (!empty($categorySlug)) {
            $query->whereHas('activeServices.category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Filter by user search radius (up to 10 km)
        $bindings = [$lat, $lng, $lat, $lat, $lng, $lat, $lat, $lng, $lat];
        $query->whereRaw("{$haversineSql} <= ?", array_merge($bindings, [$radiusKm]));

        // Order by nearest first
        $query->orderByRaw("{$haversineSql} ASC", $bindings);

        $stores = $query->get();

        $formatted = $stores->map(function (Store $store) use ($lat, $lng) {
            $distance = round((float) $store->distance_km, 2);
            $serviceRadius = (float) $store->service_radius_km;
            $isServiceable = $distance <= $serviceRadius;

            $data = $this->formatStore($store, false, $lat, $lng);
            return $data;
        });

        return $this->successResponse(
            data: $formatted,
            meta: [
                'customer_lat' => $lat,
                'customer_lng' => $lng,
                'search_radius_km' => $radiusKm,
                'total' => $formatted->count(),
                'serviceable_total' => $formatted->where('is_serviceable', true)->count(),
            ]
        );
    }

    /**
     * Helper to format Store model into consistent API response structure.
     */
    protected function formatStore(Store $store, bool $includeDetails = false, ?float $clientLat = null, ?float $clientLng = null): array
    {
        $services = $store->activeServices->map(function ($service) {
            return [
                'id' => $service->id,
                'category_id' => $service->category_id,
                'category_name' => $service->category?->name,
                'category_slug' => $service->category?->slug,
                'name' => $service->name,
                'pricing_unit' => $service->pricing_unit,
                'price_per_unit' => (int) $service->price_per_unit,
                'minimum_charge' => (int) $service->minimum_charge,
                'estimated_duration_hours' => (int) $service->estimated_duration_hours,
                'is_active' => (bool) $service->is_active,
            ];
        });

        $categories = $store->activeServices
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ]);

        $ratingAvg = isset($store->ratings_avg_score) ? (float) $store->ratings_avg_score : null;
        $ratingCount = isset($store->ratings_count) ? (int) $store->ratings_count : 0;

        $topServices = $store->activeServices->take(3)->pluck('name')->values()->all();
        $activeServicesCount = isset($store->active_services_count)
            ? (int) $store->active_services_count
            : $store->activeServices->count();

        // Min price from SQL aggregation withMin if available, or fallback to activeServices min
        $startingPrice = isset($store->starting_price)
            ? (int) $store->starting_price
            : ($services->min('price_per_unit') ?? null);

        $lowestService = $store->activeServices->firstWhere('price_per_unit', $startingPrice);
        $startingUnit = $lowestService ? $lowestService->pricing_unit : 'kg';

        $hoursInfo = $this->computeTodayHoursAndOpenStatus($store->opening_hours);
        $shortAddress = $this->extractShortAddress($store->address_text);

        $hasClientCoords = ($clientLat !== null && $clientLng !== null);

        $response = [
            'id' => $store->id,
            'name' => $store->name,
            'nama' => $store->name,
            'slug' => Str::slug($store->name),
            'description' => $store->description,
            'address_text' => $store->address_text,
            'short_address' => $shortAddress,
            'alamat_singkat' => $shortAddress,
            'latitude' => $store->latitude !== null ? (float) $store->latitude : null,
            'longitude' => $store->longitude !== null ? (float) $store->longitude : null,
            'service_radius_km' => (float) $store->service_radius_km,
            'opening_hours' => $store->opening_hours,
            'opening_hours_today' => $hoursInfo['today_hours'],
            'jam_buka_tutup_hari_ini' => $hoursInfo['formatted'],
            'is_open_now' => $hoursInfo['is_open_now'],
            'status_buka_sekarang' => $hoursInfo['is_open_now'],
            'approval_status' => $store->approval_status,
            'operational_status' => $store->operational_status,
            'categories' => $categories,
            'services' => $services,
            'top_services' => $topServices,
            'tiga_layanan_teratas' => $topServices,
            'services_count' => $activeServicesCount,
            'active_services_count' => $activeServicesCount,
            'jumlah_layanan_aktif' => $activeServicesCount,
            'starting_price' => $startingPrice !== null ? (int) $startingPrice : null,
            'harga_mulai_dari' => $startingPrice !== null ? (int) $startingPrice : null,
            'starting_unit' => $startingUnit,
            'rating' => ($ratingCount > 0 && $ratingAvg !== null) ? round($ratingAvg, 1) : null,
            'rating_average' => ($ratingCount > 0 && $ratingAvg !== null) ? round($ratingAvg, 1) : null,
            'reviews_count' => $ratingCount > 0 ? $ratingCount : null,
            'jumlah_ulasan' => $ratingCount > 0 ? $ratingCount : null,
            'rating_count' => $ratingCount,
            'created_at' => $store->created_at?->toIso8601String(),
        ];

        // Jarak hanya jika koordinat lat/lng dikirim client
        if ($hasClientCoords && isset($store->distance_km)) {
            $distance = round((float) $store->distance_km, 2);
            $response['distance_km'] = $distance;
            $response['jarak'] = $distance;
            $response['is_serviceable'] = $distance <= (float) $store->service_radius_km;
        }

        return $response;
    }

    /**
     * Compute today's opening hours and current open/closed status in Asia/Jakarta timezone.
     */
    protected function computeTodayHoursAndOpenStatus(?array $openingHours): array
    {
        if (empty($openingHours)) {
            return [
                'today_hours' => null,
                'is_open_now' => false,
                'formatted' => 'Tutup hari ini',
            ];
        }

        $now = Carbon::now('Asia/Jakarta');
        $daysMap = [
            0 => 'minggu',
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
        ];
        $dayKey = $daysMap[$now->dayOfWeek];

        $todayStr = $openingHours[$dayKey] ?? null;
        if (!$todayStr || strcasecmp(trim($todayStr), 'tutup') === 0 || strcasecmp(trim($todayStr), 'closed') === 0) {
            return [
                'today_hours' => null,
                'is_open_now' => false,
                'formatted' => 'Tutup hari ini',
            ];
        }

        $matches = [];
        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $todayStr, $matches)) {
            $openTime = $matches[1];
            $closeTime = $matches[2];
            $currentTime = $now->format('H:i');

            if ($openTime <= $closeTime) {
                $isOpen = ($currentTime >= $openTime && $currentTime < $closeTime);
            } else {
                $isOpen = ($currentTime >= $openTime || $currentTime < $closeTime);
            }

            return [
                'today_hours' => [
                    'open' => $openTime,
                    'close' => $closeTime,
                    'raw' => $todayStr,
                ],
                'is_open_now' => $isOpen,
                'formatted' => $todayStr,
            ];
        }

        return [
            'today_hours' => ['raw' => $todayStr],
            'is_open_now' => false,
            'formatted' => $todayStr,
        ];
    }

    /**
     * Extract concise address (kecamatan / kota) from full address text.
     */
    protected function extractShortAddress(?string $addressText): string
    {
        if (!$addressText) {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $addressText))));
        if (count($parts) <= 1) {
            return $addressText;
        }

        $cleanParts = [];
        foreach ($parts as $part) {
            // Skip 5-digit postal code
            if (preg_match('/^\d{5}$/', $part)) {
                continue;
            }
            $cleanParts[] = $part;
        }

        $c = count($cleanParts);
        if ($c === 0) {
            return $addressText;
        }

        $kecamatanPart = null;
        $kotaPart = null;

        foreach ($cleanParts as $part) {
            if (preg_match('/\b(kota|kabupaten|kab\.)\b/i', $part)) {
                $kotaPart = $part;
            } elseif (preg_match('/\b(kecamatan|kec\.)\b/i', $part)) {
                $kecamatanPart = $part;
            }
        }

        if ($kecamatanPart && $kotaPart) {
            return "{$kecamatanPart}, {$kotaPart}";
        }

        if ($kotaPart) {
            $idx = array_search($kotaPart, $cleanParts);
            if ($idx !== false && $idx > 0) {
                return "{$cleanParts[$idx - 1]}, {$kotaPart}";
            }
            return $kotaPart;
        }

        if ($c >= 3) {
            return $cleanParts[$c - 2] . ', ' . $cleanParts[$c - 1];
        }

        return implode(', ', $cleanParts);
    }
}

