<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Courier Assignment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for courier assignment logic including timeouts,
    | rejection limits, and GPS retention settings.
    |
    */

    // Timeout in minutes before an order goes to exception flow if no courier available
    'assignment_timeout' => env('COURIER_ASSIGNMENT_TIMEOUT', 30),

    // Maximum number of rejections before courier is flagged for monitoring
    'max_rejections' => env('COURIER_MAX_REJECTIONS', 3),

    // Maximum pickup attempts before order is marked as pickup_failed
    'max_pickup_attempts' => env('MAX_PICKUP_REATTEMPT', 3),

    // Maximum delivery attempts before order is marked as delivery_failed
    'max_delivery_attempts' => env('MAX_DELIVERY_REATTEMPT', 3),

    // GPS data retention period in days
    'gps_retention_period' => env('GPS_RETENTION_PERIOD', 30),

    // Search radius in kilometers for finding nearby couriers
    'courier_search_radius_km' => env('COURIER_SEARCH_RADIUS_KM', 5),

    // Maximum number of courier candidates to consider for assignment
    'max_assignment_attempts' => env('MAX_ASSIGNMENT_ATTEMPTS', 10),
];