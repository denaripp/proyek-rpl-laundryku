<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Assistant & Chatbot Configuration (OpenRouter / OpenAI compatible)
    |--------------------------------------------------------------------------
    | The assistant is strictly read-oriented and communicates with the model
    | provider configured below. If the key is missing or the provider fails,
    | the assistant safely degrades to customer-service contact info without
    | breaking core marketplace flows.
    |
    */

    'provider' => env('AI_PROVIDER', 'openrouter'),

    // API key for OpenRouter (or OpenAI)
    'api_key' => env('OPENROUTER_API_KEY', env('OPENAI_API_KEY')),
    'openai_api_key' => env('OPENROUTER_API_KEY', env('OPENAI_API_KEY')),

    // Base URL for Chat Completions endpoint (defaults to OpenRouter)
    'base_url' => env('OPENROUTER_BASE_URL', env('OPENAI_BASE_URL', 'https://openrouter.ai/api/v1')),

    // Default model (defaults to free tier model on OpenRouter)
    'model' => env('OPENROUTER_MODEL', env('AI_MODEL', env('OPENAI_MODEL', 'liquid/lfm-2.5-2.6b:free'))),

    // Router model for fallback intent classification (defaults to same model as main chat)
    'router_model' => env('AI_ROUTER_MODEL', env('OPENROUTER_MODEL', env('AI_MODEL', 'liquid/lfm-2.5-2.6b:free'))),

    'http_referer' => env('AI_HTTP_REFERER', env('APP_URL', 'http://localhost:3000')),
    'x_title' => env('AI_X_TITLE', env('APP_NAME', 'LaundryKu')),

    // When true, APP_ENV is local, and key is missing/placeholder, uses local mock replies.
    // Set to false to force real API provider calls.
    'mock_in_local' => (bool) env('AI_MOCK_IN_LOCAL', false),

    // Per-user message limit per hour (DB-backed).
    'rate_limit_per_hour' => (int) env('AI_RATE_LIMIT_PER_HOUR', 30),

    // How many recent conversation messages are sent as context (sliding window).
    'max_history' => (int) env('AI_MAX_HISTORY', 6),

    // Max function-calling round-trips per user message (anti-loop).
    'max_tool_rounds' => 5,

    // HTTP timeout for provider call in seconds.
    'timeout' => (int) env('AI_TIMEOUT', 30),

    // Pricing in USD per 1M tokens, used for cost logging.
    'pricing' => [
        'liquid/lfm-2.5-2.6b:free' => ['input' => 0.0, 'output' => 0.0],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
    ],
];