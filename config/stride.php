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
        'driver' => env('STRIDE_COACH_DRIVER', 'anthropic'), // anthropic | gemini | ollama | local | fake

        // Model ids are per-purpose and provider-specific. Switching driver means
        // switching these too, e.g. for Gemini:
        //   STRIDE_COACH_DRIVER=gemini
        //   STRIDE_COACH_MODEL=gemini-2.5-flash
        //   STRIDE_COACH_SUMMARY_MODEL=gemini-2.5-flash
        //   STRIDE_COACH_GENERATE_MODEL=gemini-2.5-pro
        // (the gemini driver rejects non-gemini-* ids to fail fast on a mismatch).
        'model' => env('STRIDE_COACH_MODEL', 'claude-haiku-4-5'),
        'summary_model' => env('STRIDE_COACH_SUMMARY_MODEL', 'claude-haiku-4-5'),
        'generate_model' => env('STRIDE_COACH_GENERATE_MODEL', 'claude-sonnet-4-6'),

        // Safety-net timeout for a single plan-generation call. Generation now runs
        // one small call per session, which finishes in ~15s on CPU Ollama, so this
        // generous cap effectively lets a local model run to completion without ever
        // hanging the whole request (a stuck connection still eventually errors out).
        'generate_timeout' => (int) env('STRIDE_COACH_GENERATE_TIMEOUT', 180),

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

        // Driver "gemini": Google Generative Language API. Key in
        // services.gemini.api_key (GEMINI_API_KEY). The model id comes from the
        // per-purpose config above (must be gemini-*); this block only tunes the
        // transport. url is overridable mainly so tests can fake the host.
        'gemini' => [
            'url' => env('STRIDE_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => (int) env('STRIDE_GEMINI_TIMEOUT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model pricing (USD per million tokens)
    |--------------------------------------------------------------------------
    |
    | Used to estimate cost_usd on each ai_usage row. Cached reads are ~10% of
    | the input rate, cache writes ~125%. Update if provider pricing changes.
    | Gemini rows only ever populate cache_read (implicit caching), so its
    | cache_write rate is nominal. Verify current Google prices before relying
    | on these for billing.
    |
    */

    'pricing' => [
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cache_write' => 1.25, 'cache_read' => 0.10],
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00, 'cache_write' => 3.75, 'cache_read' => 0.30],
        // Gemini output rates include thinking tokens (3.x are reasoning models);
        // cache_read ≈ 25% of input (implicit caching). Verify against current prices.
        'gemini-3.5-flash' => ['input' => 1.50, 'output' => 9.00, 'cache_write' => 1.50, 'cache_read' => 0.375],
        // Pro id on the Developer API is gemini-3.1-pro-preview (bare gemini-3.1-pro 404s);
        // gemini-pro-latest is the always-valid alias.
        'gemini-3.1-pro-preview' => ['input' => 2.00, 'output' => 12.00, 'cache_write' => 2.00, 'cache_read' => 0.50],
        'gemini-pro-latest' => ['input' => 2.00, 'output' => 12.00, 'cache_write' => 2.00, 'cache_read' => 0.50],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50, 'cache_write' => 0.30, 'cache_read' => 0.075],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00, 'cache_write' => 1.25, 'cache_read' => 0.31],
        'default' => ['input' => 1.00, 'output' => 5.00, 'cache_write' => 1.25, 'cache_read' => 0.10],
    ],

];
