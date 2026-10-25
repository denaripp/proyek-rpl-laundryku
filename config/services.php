<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY', ''),
        'cache_ttl' => env('GOOGLE_MAPS_CACHE_TTL', 86400),
    ],

    'fonnte' => [
        'api_token' => env('FONNTE_API_TOKEN'),
        'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        'webhook_token' => env('FONNTE_WEBHOOK_TOKEN'),
    ],

    'otp' => [
        'debug_response' => filter_var(env('OTP_DEBUG_RESPONSE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'LaundryKu/1.0 (contact@laundryku.test)'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 5),
        'cache_ttl' => (int) env('NOMINATIM_CACHE_TTL', 86400),
    ],

];