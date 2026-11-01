<?php

return [
    'timezone' => 'Europe/Bratislava',
    'private_disk' => 'local',
    'holidays' => array_filter(explode(',', env('SUPPORT_HOLIDAYS', ''))),
    'github' => [
        'app_id' => env('SUPPORT_GITHUB_APP_ID'),
        'private_key' => env('SUPPORT_GITHUB_PRIVATE_KEY'),
        'webhook_secret' => env('SUPPORT_GITHUB_WEBHOOK_SECRET'),
    ],
    'sentry' => [
        'organization' => env('SUPPORT_SENTRY_ORGANIZATION'),
        'webhook_secret' => env('SUPPORT_SENTRY_WEBHOOK_SECRET'),
    ],
    'telegram' => [
        'bot_token' => env('SUPPORT_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('SUPPORT_TELEGRAM_CHAT_ID'),
    ],
    'turnstile' => [
        'site_key' => env('SUPPORT_TURNSTILE_SITE_KEY'),
        'secret' => env('SUPPORT_TURNSTILE_SECRET'),
    ],
];
