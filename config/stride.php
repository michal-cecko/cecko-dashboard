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

        // Generation (recommend/questions/session) needs a bigger output budget than
        // chat: on Gemini "thinking" models the budget is shared by thinking + JSON,
        // so a small cap truncates the plan. Give it ample room; thinking is bounded
        // separately (ai.gemini.generate_thinking_budget) so the JSON always fits.
        'generate_max_tokens' => (int) env('STRIDE_COACH_GENERATE_MAX_TOKENS', 4096),

        // How many recent raw messages to send verbatim; older turns are folded
        // into the conversation's rolling summary.
        'recent_turns' => (int) env('STRIDE_COACH_RECENT_TURNS', 12),

        // Summarise once unsummarised messages beyond the kept window exceed this.
        'summary_threshold' => (int) env('STRIDE_COACH_SUMMARY_THRESHOLD', 20),

        // Tool-use loop safety cap.
        'max_tool_iterations' => (int) env('STRIDE_COACH_MAX_TOOL_ITERATIONS', 4),

        // Per-user daily cap on coach messages during testing.
        'daily_message_quota' => (int) env('STRIDE_COACH_DAILY_QUOTA', 50),

    ],

];
