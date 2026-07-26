<?php

namespace App\Services\Common\Ai;

/**
 * Per-call credentials for a driver, so the same provider class can serve the
 * app's own key (free tier / cron) or a user's BYOK API key. When null is passed
 * to a driver it falls back to the server config, preserving the original
 * single-key behaviour.
 */
readonly class AiCredentials
{
    public function __construct(
        public ?string $apiKey = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->apiKey === null || $this->apiKey === '';
    }
}
