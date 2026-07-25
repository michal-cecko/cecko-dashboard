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

        'max_tokens' => (int) env('STRIDE_COACH_MAX_TOKENS', 2048),

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

    /*
    |--------------------------------------------------------------------------
    | Bring-your-own-model (per-user AI connection)
    |--------------------------------------------------------------------------
    |
    | Each user can connect their own provider + model (Anthropic / Gemini /
    | OpenAI) through the in-app wizard; the coach then runs on THEIR credentials.
    | Anyone who hasn't connected falls back to the free tier, which is the app's
    | own key on the coach config above (driver + models), with a lower daily cap.
    | The `providers` catalog drives the wizard's model picker and the in-chat
    | model switcher.
    |
    */

    'ai' => [
        // Allow "Log in with Claude subscription" (OAuth). OFF by default —
        // proxying a personal Claude subscription through a third-party app may
        // conflict with Anthropic's usage policies. Review ToS before enabling.
        'allow_subscription_oauth' => (bool) env('STRIDE_AI_ALLOW_SUBSCRIPTION_OAUTH', false),

        // BYOK users run on their own key/cost — 0 = unlimited.
        'byok_daily_quota' => (int) env('STRIDE_AI_BYOK_DAILY_QUOTA', 0),

        // Free tier (app-subsidised): cheap model + a tight daily cap.
        'free' => [
            'label' => 'Free (built-in)',
            'daily_quota' => (int) env('STRIDE_AI_FREE_DAILY_QUOTA', 20),
        ],

        // Selectable providers + models shown in the wizard and chat switcher.
        // `default` / `generate` are the per-purpose fallbacks when a user picks
        // a provider but not a specific model.
        'providers' => [
            'anthropic' => [
                'label' => 'Anthropic (Claude)',
                'auth_types' => ['api_key', 'oauth'],
                'models' => [
                    ['id' => 'claude-opus-4-8', 'label' => 'Claude Opus 4.8', 'tier' => 'flagship'],
                    ['id' => 'claude-sonnet-4-6', 'label' => 'Claude Sonnet 4.6', 'tier' => 'balanced'],
                    ['id' => 'claude-haiku-4-5', 'label' => 'Claude Haiku 4.5', 'tier' => 'fast'],
                ],
                'default' => 'claude-sonnet-4-6',
                'generate' => 'claude-sonnet-4-6',
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'auth_types' => ['api_key'],
                'models' => [
                    ['id' => 'gemini-2.5-pro', 'label' => 'Gemini 2.5 Pro', 'tier' => 'flagship'],
                    ['id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash', 'tier' => 'fast'],
                ],
                'default' => 'gemini-2.5-flash',
                'generate' => 'gemini-2.5-pro',
            ],
            'openai' => [
                'label' => 'OpenAI',
                'auth_types' => ['api_key'],
                'models' => [
                    ['id' => 'gpt-5', 'label' => 'GPT-5', 'tier' => 'flagship'],
                    ['id' => 'gpt-5-mini', 'label' => 'GPT-5 mini', 'tier' => 'fast'],
                    ['id' => 'gpt-4o', 'label' => 'GPT-4o', 'tier' => 'balanced'],
                ],
                'default' => 'gpt-5-mini',
                'generate' => 'gpt-5',
            ],
        ],

        // Anthropic subscription OAuth (only used when allow_subscription_oauth).
        // Endpoints + client id are env-driven; nothing is hardcoded.
        'oauth' => [
            'anthropic' => [
                'client_id' => env('STRIDE_AI_ANTHROPIC_OAUTH_CLIENT_ID'),
                'authorize_url' => env('STRIDE_AI_ANTHROPIC_OAUTH_AUTHORIZE_URL', 'https://claude.ai/oauth/authorize'),
                'token_url' => env('STRIDE_AI_ANTHROPIC_OAUTH_TOKEN_URL', 'https://console.anthropic.com/v1/oauth/token'),
                'redirect_uri' => env('STRIDE_AI_ANTHROPIC_OAUTH_REDIRECT', rtrim((string) env('APP_URL'), '/').'/api/stride/ai/connection/anthropic/callback'),
                'scope' => env('STRIDE_AI_ANTHROPIC_OAUTH_SCOPE', 'user:inference user:profile'),
                'beta_header' => 'oauth-2025-04-20',
            ],
        ],
    ],

];
