<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleMapsService implements GeocodingServiceInterface
{
    protected ?string $apiKey;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key', '');
        $this->cacheTtl = config('services.google_maps.cache_ttl', 86400);
    }

    /**
     * Geocode an address to coordinates with caching and fallback.
     */
    public function geocode(string $address): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = 'geocode:' . sha1($address);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($address) {
            try {
                $response = Http::timeout(5)
                    ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'address' => $address,
                        'key' => $this->apiKey,
                    ]);

                if ($response->successful() && $response->json('status') === 'OK') {
                    $location = $response->json('results.0.geometry.location');
                    if ($location) {
                        return [
                            'lat' => (float) $location['lat'],
                            'lng' => (float) $location['lng'],
                        ];
                    }
                }

                // Log failure but return null — fallback keeps the address text intact
                Log::warning('Geocoding failed for address', [
                    'address' => $address,
                    'status' => $response->json('status') ?? 'no_response',
                ]);

                return null;
            } catch (Exception $e) {
                Log::error('Geocoding exception', [
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Reverse geocode coordinates to address with caching.
     */
    public function reverseGeocode(float $lat, float $lng): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = 'reverse_geocode:' . sha1("{$lat},{$lng}");

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($lat, $lng) {
            try {
                $response = Http::timeout(5)
                    ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'latlng' => "{$lat},{$lng}",
                        'key' => $this->apiKey,
                    ]);

                if ($response->successful() && $response->json('status') === 'OK') {
                    return $response->json('results.0.formatted_address');
                }

                Log::warning('Reverse geocoding failed', [
                    'lat' => $lat,
                    'lng' => $lng,
                    'status' => $response->json('status') ?? 'no_response',
                ]);
                return null;
            } catch (Exception $e) {
                Log::error('Reverse geocoding exception', [
                    'lat' => $lat,
                    'lng' => $lng,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Calculate distance using Google Distance Matrix API, fallback to Haversine if API fails.
     */
    public function distance(array $origin, array $destination): ?float
    {
        // If coordinates are missing, return null
        if (empty($origin['lat']) || empty($origin['lng']) || empty($destination['lat']) || empty($destination['lng'])) {
            return null;
        }

        // If no API key, fallback directly
        if (empty($this->apiKey)) {
            return $this->haversine($origin['lat'], $origin['lng'], $destination['lat'], $destination['lng']);
        }

        // Try API first
        try {
            $response = Http::timeout(5)
                ->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins' => "{$origin['lat']},{$origin['lng']}",
                    'destinations' => "{$destination['lat']},{$destination['lng']}",
                    'key' => $this->apiKey,
                ]);

            if ($response->successful() && $response->json('status') === 'OK') {
                $element = $response->json('rows.0.elements.0');
                if ($element && $element['status'] === 'OK') {
                    return (float) $element['distance']['value'] / 1000; // meters to km
                }
            }
            // If API fails, log and fallback to Haversine
            Log::info('Distance Matrix API fallback to Haversine', [
                'status' => $response->json('status') ?? 'no_response',
            ]);
        } catch (Exception $e) {
            Log::error('Distance API exception, using Haversine', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: Haversine formula
        return $this->haversine($origin['lat'], $origin['lng'], $destination['lat'], $destination['lng']);
    }

    /**
     * Haversine formula to calculate distance in kilometers.
     */
    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}