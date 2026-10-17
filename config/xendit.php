<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Xendit API Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('XENDIT_API_KEY'),
    'webhook_secret' => env('XENDIT_WEBHOOK_SECRET'),
    'api_url' => env('XENDIT_API_URL', 'https://api.xendit.co'),
    'invoice_expiry_minutes' => env('XENDIT_INVOICE_EXPIRY', 60),
    'callback_url' => env('XENDIT_CALLBACK_URL'),
];