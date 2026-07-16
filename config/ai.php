<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider transports
    |--------------------------------------------------------------------------
    |
    | Connection settings for the shared AI drivers (App\Services\Common\Ai).
    | Credentials stay in config/services.php per Laravel convention. The
    | legacy STRIDE_* env names keep working as fallbacks.
    |
    */

    // Display FX for showing AI cost in EUR (cost is computed in USD from the
    // pricing map). Rough is fine — it's an estimate, not billing.
    'eur_per_usd' => (float) env('AI_EUR_PER_USD', env('STRIDE_EUR_PER_USD', 0.92)),

    'gemini' => [
        'url' => env('AI_GEMINI_URL', env('STRIDE_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta')),
        'timeout' => (int) env('AI_GEMINI_TIMEOUT', env('STRIDE_GEMINI_TIMEOUT', 60)),
        // Gemini 3.x thinking cap for structured-JSON generation purposes so
        // reasoning tokens never truncate the output.
        'generate_thinking_budget' => (int) env('AI_GEMINI_THINKING_BUDGET', env('STRIDE_COACH_GENERATE_THINKING_BUDGET', 2048)),
    ],

    'ollama' => [
        'url' => env('AI_OLLAMA_URL', env('STRIDE_OLLAMA_URL', 'http://localhost:11434')),
        // Prefer non-thinking instruct models: reasoning variants burn
        // hundreds of tokens before answering — minutes on CPU.
        'model' => env('AI_OLLAMA_MODEL', env('STRIDE_OLLAMA_MODEL', 'qwen3:4b-instruct-2507-q4_K_M')),
        'timeout' => (int) env('AI_OLLAMA_TIMEOUT', env('STRIDE_OLLAMA_TIMEOUT', 300)),
        // Sent as Ollama's "think" parameter when not null. Leave null for
        // models that reject it; false only works on hybrid thinkers.
        'think' => env('AI_OLLAMA_THINK', env('STRIDE_OLLAMA_THINK')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model pricing (USD per million tokens)
    |--------------------------------------------------------------------------
    |
    | Single source of truth for estimating cost_usd on AI usage rows across
    | all panels (Stride, Garáž, …). Cached reads are ~10% of the input rate,
    | cache writes ~125%. Update if provider pricing changes. Gemini rows only
    | ever populate cache_read (implicit caching), so its cache_write rate is
    | nominal. Verify current Google prices before relying on these for billing.
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
