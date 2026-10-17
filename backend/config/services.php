<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'public_key' => env('XENDIT_PUBLIC_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_VERIFICATION_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'api_key' => env('OPENAI_API_KEY', env('AI_API_KEY')),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('AI_TIMEOUT', 15),
    ],

    'fonnte' => [
        'api_token' => env('FONNTE_API_TOKEN'),
        'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        'webhook_token' => env('FONNTE_WEBHOOK_TOKEN'),
    ],

];

