<?php

$fallbackModels = env('AI_MODEL_FALLBACK');
$fallbackList = is_string($fallbackModels) && trim($fallbackModels) !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $fallbackModels))))
    : ['liquid/lfm-2.5-2.6b:free', 'nvidia/nemotron-3.5-lightning:free'];

return [
    /*
    |--------------------------------------------------------------------------
    | AI Assistant & Chatbot Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for AI provider and chatbot assistant.
    | Mock response is only active when mock_in_local is true and the environment
    | is strictly local.
    |
    */

    'provider' => env('AI_PROVIDER', 'openrouter'),

    'mock_in_local' => filter_var(env('AI_MOCK_IN_LOCAL', false), FILTER_VALIDATE_BOOLEAN),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', env('AI_API_KEY')),
        'base_url' => env('OPENAI_BASE_URL', env('AI_BASE_URL', 'https://openrouter.ai/api/v1')),
        'model' => env('AI_MODEL', env('OPENAI_MODEL', 'google/gemma-4-26b-a4b-it:free')),
        'fallback_models' => $fallbackList,
        'timeout' => (int) env('AI_TIMEOUT', 15),
    ],

    'http_referer' => env('AI_HTTP_REFERER', env('APP_URL', 'http://localhost:8000')),
    'x_title' => env('AI_X_TITLE', env('APP_NAME', 'LaundryKu')),

    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT', 10),

    'daily_limit' => (int) env('AI_DAILY_LIMIT', 150),
];
