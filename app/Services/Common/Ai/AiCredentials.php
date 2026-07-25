<?php

namespace App\Services\Common\Ai;

/**
 * Per-call credentials for a driver, so the same provider class can serve the
 * app's own key (free tier / cron), a user's BYOK API key, or a user's OAuth
 * bearer token. When null is passed to a driver it falls back to the server
 * config, preserving the original single-key behaviour.
 *
 * `apiKey` — a provider API key (x-api-key / x-goog-api-key / Bearer sk-…).
 * `oauthToken` — an OAuth access token (Anthropic subscription); when present it
 *   is sent as `Authorization: Bearer` with the oauth beta header instead of a key.
 */
readonly class AiCredentials
{
    public function __construct(
        public ?string $apiKey = null,
        public ?string $oauthToken = null,
    ) {}

    public function isEmpty(): bool
    {
        return ($this->apiKey === null || $this->apiKey === '')
            && ($this->oauthToken === null || $this->oauthToken === '');
    }

    public function usesOAuth(): bool
    {
        return $this->oauthToken !== null && $this->oauthToken !== '';
    }
}
