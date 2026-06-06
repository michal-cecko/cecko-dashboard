<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI coach
    |--------------------------------------------------------------------------
    |
    | The coach speaks to users through a provider-agnostic gateway. Default to
    | a cheap, fast model for chat and only reach for a stronger one when
    | generating whole sessions. Driver "fake" is used by the test suite.
    |
    */

    'coach' => [
        'driver' => env('STRIDE_COACH_DRIVER', 'anthropic'), // anthropic | ollama | local | fake

        'model' => env('STRIDE_COACH_MODEL', 'claude-haiku-4-5'),
        'summary_model' => env('STRIDE_COACH_SUMMARY_MODEL', 'claude-haiku-4-5'),
        'generate_model' => env('STRIDE_COACH_GENERATE_MODEL', 'claude-sonnet-4-6'),

        'max_tokens' => (int) env('STRIDE_COACH_MAX_TOKENS', 1024),

        // How many recent raw messages to send verbatim; older turns are folded
        // into the conversation's rolling summary.
        'recent_turns' => (int) env('STRIDE_COACH_RECENT_TURNS', 12),

        // Summarise once unsummarised messages beyond the kept window exceed this.
        'summary_threshold' => (int) env('STRIDE_COACH_SUMMARY_THRESHOLD', 20),

        // Tool-use loop safety cap.
        'max_tool_iterations' => (int) env('STRIDE_COACH_MAX_TOOL_ITERATIONS', 4),

        // Per-user daily cap on coach messages during testing.
        'daily_message_quota' => (int) env('STRIDE_COACH_DAILY_QUOTA', 50),

        // Driver "ollama": free local inference for dev. The model must support
        // tool use (qwen3, llama3.1, mistral-nemo, ...) and serves every purpose
        // — chat and summary alike. Usage rows are logged with cost 0.
        'ollama' => [
            'url' => env('STRIDE_OLLAMA_URL', 'http://localhost:11434'),
            // Prefer non-thinking instruct models: reasoning variants burn
            // hundreds of tokens before answering — minutes on CPU.
            'model' => env('STRIDE_OLLAMA_MODEL', 'qwen3:4b-instruct-2507-q4_K_M'),
            'timeout' => (int) env('STRIDE_OLLAMA_TIMEOUT', 300),
            // Sent as Ollama's "think" parameter when not null. Leave null for
            // models that reject it; false only works on hybrid thinkers.
            'think' => env('STRIDE_OLLAMA_THINK'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model pricing (USD per million tokens)
    |--------------------------------------------------------------------------
    |
    | Used to estimate cost_usd on each ai_usage row. Cached reads are ~10% of
    | the input rate, cache writes ~125%. Update if Anthropic pricing changes.
    |
    */

    'pricing' => [
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cache_write' => 1.25, 'cache_read' => 0.10],
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00, 'cache_write' => 3.75, 'cache_read' => 0.30],
        'default' => ['input' => 1.00, 'output' => 5.00, 'cache_write' => 1.25, 'cache_read' => 0.10],
    ],

];
