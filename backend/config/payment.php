<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Expiration Configuration (Minutes)
    |--------------------------------------------------------------------------
    | Shared expiration windows used across XenditService, OrderController,
    | and ExpireUnpaidOrdersCommand to prevent discrepancy.
    */
    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 1440), // 24 hours default
    'va_expiry_minutes' => (int) env('PAYMENT_VA_EXPIRY_MINUTES', 1440), // 24 hours
    'qris_expiry_minutes' => (int) env('PAYMENT_QRIS_EXPIRY_MINUTES', 30), // 30 minutes
];
