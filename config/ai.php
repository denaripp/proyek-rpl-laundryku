<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Assistant configuration
    |--------------------------------------------------------------------------
    | The assistant is strictly read-oriented and talks to the outside world
    | only through the OpenAI API configured here. If the key is missing or
    | the provider is down, the assistant degrades to human-CS contact info;
    | the core marketplace keeps working normally.
    */

    'openai_api_key' => env('OPENAI_API_KEY'),

    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

    // When true and APP_ENV is local and OPENAI_API_KEY is empty, responds with local simulated replies.
    'mock_in_local' => (bool) env('AI_MOCK_IN_LOCAL', true),

    // Per-user message limit per hour (DB-backed; see AI_RATE_LIMIT_PER_HOUR config).
    'rate_limit_per_hour' => (int) env('AI_RATE_LIMIT_PER_HOUR', 30),

    // How many of the user's recent messages are sent as context.
    'max_history' => 20,

    // Max function-calling round-trips per user message (anti-loop).
    'max_tool_rounds' => 5,

    // HTTP timeout for the provider call (seconds).
    'timeout' => 30,

    // Pricing in USD per 1M tokens, used only for cost monitoring.
    'pricing' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
    ],
];