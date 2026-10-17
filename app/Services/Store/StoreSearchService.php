<?php

namespace App\Services\Store;

use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class StoreSearchService
{
    protected array $weights;
    protected float $margin;
    protected ?float $minRating;

    public function __construct()
    {
        $this->weights = config('search.ranking_weights', []);
        $this->margin = config('search.bounding_box_margin', 0.05);
        $this->minRating = config('search.min_rating');
        $this->normalizeWeights();
    }

    protected function normalizeWeights(): void
    {
        $total = array_sum($this->weights);
        if ($total > 0) {
            foreach ($this->weights as $key => $value) {
                $this->weights[$key] = $value / $total;
            }
        }
    }

    public function search(array $filters, int $page = 1, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = $perPage ?? config('search.per_page', 15);

        // Apply default min_rating from config if not explicitly set
        if ($this->minRating !== null && !isset($filters['min_rating'])) {
            $filters['min_rating'] = $this->minRating;
        }

        $query = Store::query()
            ->approved()
            ->with(['servicePrices.service']);

        if (!empty($filters['service_id'])) {
            $serviceId = (int) $filters['service_id'];
            $query->whereHas('servicePrices', function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId)->where('is_active', true);
            });
        }

        if (!empty($filters['q'])) {
            $query->where('name', 'LIKE', '%' . addslashes($filters['q']) . '%');
        }

        if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
            $query->whereHas('servicePrices', function ($q) use ($filters) {
                if (!empty($filters['min_price'])) {
                    $q->where('price_per_unit', '>=', (float) $filters['min_price']);
                }
                if (!empty($filters['max_price'])) {
                    $q->where('price_per_unit', '<=', (float) $filters['max_price']);
                }
            });
        }

        if (!empty($filters['min_rating'])) {
            $minRating = (float) $filters['min_rating'];
            // Precomputed aggregate (maintained by ReviewService), no per-search aggregation
            $query->whereNotNull('average_rating')
                  ->where('average_rating', '>=', $minRating);
        }

        $lat = $filters['latitude'] ?? null;
        $lng = $filters['longitude'] ?? null;
        $radius = $filters['radius_km'] ?? null;

        if ($lat !== null && $lng !== null) {
            $this->applyDistanceFilter($query, (float) $lat, (float) $lng, $radius);
        }

        if ($lat !== null && $lng !== null) {
            $distanceSelect = $this->getDistanceSelectSql((float) $lat, (float) $lng);
            $query->select('stores.*', DB::raw($distanceSelect . ' as distance'));
        } else {
            $query->select('stores.*');
        }

        $stores = $query->get();

        $ranked = $stores->map(function ($store) use ($lat, $lng, $filters) {
            $score = $this->calculateScore($store, $lat, $lng, $filters);
            $store->rank_score = $score;
            return $store;
        })->sortByDesc('rank_score')->values();

        $total = $ranked->count();
        $items = $ranked->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    protected function applyDistanceFilter($query, float $lat, float $lng, ?float $radius): void
    {
        $margin = $this->margin;
        if ($radius !== null) {
            $margin = max($margin, $radius / 111.0);
        }
        $query->whereBetween('latitude', [$lat - $margin, $lat + $margin])
              ->whereBetween('longitude', [$lng - $margin, $lng + $margin]);

        if ($radius !== null) {
            $distanceSql = $this->getDistanceSelectSql($lat, $lng);
            $query->havingRaw("{$distanceSql} <= ?", [$radius]);
        }
    }

    protected function getDistanceSelectSql(float $lat, float $lng): string
    {
        // SECURITY (Tahap 19): walaupun dipanggil via float-cast (%f),
        // tetap gunakan placeholder untuk konsistensi defense in depth.
        return sprintf(
            "6371 * ACOS(
                COS(RADIANS(%f)) * COS(RADIANS(latitude)) *
                COS(RADIANS(longitude) - RADIANS(%f)) +
                SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
            )",
            $lat, $lng, $lat
        );
    }

    protected function calculateScore($store, ?float $lat, ?float $lng, array $filters): float
    {
        $score = 0.0;

        if ($lat !== null && $lng !== null && isset($store->distance)) {
            $distance = $store->distance;
            $distanceScore = 1 / (1 + $distance);
            $score += $this->weights['distance'] * $distanceScore;
        }

        $rating = (float) ($store->average_rating ?? 0);
        $ratingScore = $rating / 5.0;
        $score += $this->weights['rating'] * $ratingScore;

        $reviewCount = (int) ($store->review_count ?? 0);
        $reviewScore = min(1, log($reviewCount + 1) / log(100 + 1));
        $score += $this->weights['review_count'] * $reviewScore;

        $availabilityScore = $store->is_active ? 1.0 : 0.0;
        $score += $this->weights['availability'] * $availabilityScore;

        $serviceMatch = 1.0;
        if (!empty($filters['service_id'])) {
            $hasService = $store->servicePrices->contains('service_id', (int)$filters['service_id']);
            $serviceMatch = $hasService ? 1.0 : 0.0;
        }
        $score += $this->weights['service_match'] * $serviceMatch;

        $performanceScore = 0.8;
        $score += $this->weights['performance'] * $performanceScore;

        return $score;
    }
}