<?php

namespace App\Services\Maps;

interface GeocodingServiceInterface
{
    /**
     * Geocode an address to coordinates.
     *
     * @param string $address
     * @return array|null ['lat' => float, 'lng' => float] or null on failure
     */
    public function geocode(string $address): ?array;

    /**
     * Reverse geocode coordinates to address.
     *
     * @param float $lat
     * @param float $lng
     * @return string|null Address string or null on failure
     */
    public function reverseGeocode(float $lat, float $lng): ?string;

    /**
     * Calculate distance between two coordinates in kilometers.
     *
     * @param array $origin ['lat' => float, 'lng' => float]
     * @param array $destination ['lat' => float, 'lng' => float]
     * @return float|null Distance in km, or null if cannot calculate
     */
    public function distance(array $origin, array $destination): ?float;
}