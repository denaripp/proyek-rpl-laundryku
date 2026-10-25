<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NominatimGeocodingService
{
    protected string $baseUrl;
    protected string $userAgent;
    protected int $timeout;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');
        $this->userAgent = config('services.nominatim.user_agent', 'LaundryKu/1.0 (contact@laundryku.test)');
        $this->timeout = (int) config('services.nominatim.timeout', 5);
        $this->cacheTtl = (int) config('services.nominatim.cache_ttl', 86400);
    }

    /**
     * Reverse geocode latitude and longitude to structured Indonesian address.
     *
     * @param float $lat Latitude between -90 and 90
     * @param float $lng Longitude between -180 and 180
     * @return array{
     *     latitude: float,
     *     longitude: float,
     *     formatted_address: string,
     *     road: ?string,
     *     kelurahan: ?string,
     *     kecamatan: ?string,
     *     city: ?string,
     *     province: ?string,
     *     postal_code: ?string
     * }|null
     */
    public function reverseGeocodeStructured(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        // Round to 4 decimal places (~11 meters precision) for cache deduplication
        $roundedLat = round($lat, 4);
        $roundedLng = round($lng, 4);
        $cacheKey = "nominatim:reverse:{$roundedLat}:{$roundedLng}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            // Keep original requested coordinates in returned payload
            $cached['latitude'] = $lat;
            $cached['longitude'] = $lng;
            return $cached;
        }

        // Global rate limit: max 1 request per second to respect OSM Nominatim usage policy
        $this->throttleGlobalRequest();

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => $this->userAgent,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/reverse", [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'addressdetails' => 1,
                ]);

            if (!$response->successful()) {
                Log::warning('Nominatim reverse geocode returned non-success response', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $json = $response->json();
            if (!is_array($json) || empty($json)) {
                return null;
            }

            $parsed = $this->parseNominatimResponse($json, $lat, $lng);
            if ($parsed) {
                Cache::put($cacheKey, $parsed, $this->cacheTtl);
            }

            return $parsed;
        } catch (Throwable $e) {
            Log::error('Nominatim reverse geocode exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Enforce maximum 1 request per second across all workers.
     */
    protected function throttleGlobalRequest(): void
    {
        $lastTime = (float) Cache::get('nominatim_last_request_timestamp', 0);
        $now = microtime(true);
        $elapsed = $now - $lastTime;

        if ($elapsed < 1.0) {
            $sleepMicroseconds = (int) ((1.0 - $elapsed) * 1_000_000);
            if ($sleepMicroseconds > 0) {
                usleep($sleepMicroseconds);
            }
        }

        Cache::put('nominatim_last_request_timestamp', microtime(true), 60);
    }

    /**
     * Parse and map Nominatim address details into structured Indonesian fields.
     */
    protected function parseNominatimResponse(array $json, float $lat, float $lng): array
    {
        $addr = $json['address'] ?? [];

        $road = $addr['road']
            ?? $addr['pedestrian']
            ?? $addr['street']
            ?? $addr['residential']
            ?? $addr['path']
            ?? null;

        $houseNumber = $addr['house_number'] ?? null;

        $kelurahan = $addr['suburb']
            ?? $addr['village']
            ?? $addr['neighbourhood']
            ?? $addr['hamlet']
            ?? $addr['quarter']
            ?? null;

        $kecamatan = $addr['city_district']
            ?? $addr['district']
            ?? $addr['subdistrict']
            ?? $addr['county_subdivision']
            ?? null;

        $city = $addr['city']
            ?? $addr['municipality']
            ?? $addr['regency']
            ?? $addr['county']
            ?? $addr['town']
            ?? null;

        $province = $addr['state']
            ?? $addr['province']
            ?? $addr['region']
            ?? null;

        $postalCode = $addr['postcode'] ?? null;

        $roadWithNumber = trim(($road ?? '') . ($houseNumber ? ' No. ' . $houseNumber : ''));

        // Build human-friendly formatted address
        $parts = array_filter([
            $roadWithNumber ?: ($road ?? null),
            $kelurahan ? 'Kel. ' . $kelurahan : null,
            $kecamatan ? 'Kec. ' . $kecamatan : null,
            $city,
            $province,
            $postalCode,
        ]);

        $formattedAddress = !empty($parts)
            ? implode(', ', $parts)
            : ($json['display_name'] ?? '');

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'formatted_address' => $formattedAddress,
            'road' => $roadWithNumber ?: ($road ?? null),
            'kelurahan' => $kelurahan,
            'kecamatan' => $kecamatan,
            'city' => $city,
            'province' => $province,
            'postal_code' => $postalCode,
        ];
    }
}
