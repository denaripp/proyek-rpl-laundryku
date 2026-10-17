<?php

return [
    'ranking_weights' => [
        'distance' => 0.30,
        'rating' => 0.25,
        'review_count' => 0.10,
        'availability' => 0.15,
        'service_match' => 0.10,
        'performance' => 0.10,
    ],
    'bounding_box_margin' => 0.05,
    'min_rating' => null,
    'per_page' => 15,
];