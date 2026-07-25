<?php

namespace App\Services\Common\Ai\Auth;

use App\Models\Common\AiConnection;
use App\Models\Common\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Anthropic subscription (Claude Pro/Max) login via OAuth 2.0 + PKCE — the same
 * shape Claude Code uses. Feature-flagged by stride.ai.allow_subscription_oauth
 * (default off) because proxying a personal subscription through a third-party
 * app may conflict with Anthropic's usage policies.
 *
 * Endpoints, client id, redirect and scope are all env-driven (config/stride.php
 * → ai.oauth.anthropic); nothing is hardcoded. Tokens are stored (encrypted) on
 * the user's AiConnection and auto-refreshed before expiry.
 */
class AnthropicOAuthService
{
    private const CACHE_PREFIX = 'stride_ai_oauth:';

    public function isEnabled(): bool
    {
        return (bool) config('stride.ai.allow_subscription_oauth')
            && ! empty($this->cfg('client_id'));
    }

    /**
     * Start the flow: mint a PKCE verifier + state, stash them, and return the
     * authorize URL the app should open in an in-app browser.
     *
     * @return array{url: string, state: string}
     */
    public function beginAuthorization(User $user): array
    {
        $this->assertEnabled();

        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = Str::random(40);

        Cache::put(self::CACHE_PREFIX.$state, [
            'verifier' => $verifier,
            'user_id' => $user->id,
        ], now()->addMinutes(10));

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->cfg('client_id'),
            'redirect_uri' => $this->cfg('redirect_uri'),
            'scope' => $this->cfg('scope'),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return ['url' => rtrim((string) $this->cfg('authorize_url'), '?').'?'.$query, 'state' => $state];
    }

    /**
     * Finish the flow: validate state, exchange the code, and persist the tokens
     * onto the user's AiConnection (provider=anthropic, auth_type=oauth).
     */
    public function handleCallback(string $code, string $state): AiConnection
    {
        $this->assertEnabled();

        $stash = Cache::pull(self::CACHE_PREFIX.$state);
        if (! is_array($stash) || empty($stash['verifier']) || empty($stash['user_id'])) {
            throw new RuntimeException('OAuth state expired or invalid. Please try connecting again.');
        }

        $tokens = $this->requestTokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->cfg('redirect_uri'),
            'client_id' => $this->cfg('client_id'),
            'code_verifier' => $stash['verifier'],
        ]);

        return AiConnection::updateOrCreate(
            ['user_id' => $stash['user_id']],
            [
                'provider' => 'anthropic',
                'auth_type' => 'oauth',
                'credentials' => $tokens,
                'status' => 'active',
                'last_verified_at' => now(),
                'last_error' => null,
            ],
        );
    }

    /** Refresh the access token in place if it is missing or within 60s of expiry. */
    public function ensureFresh(AiConnection $connection): void
    {
        if ($connection->auth_type !== 'oauth') {
            return;
        }

        $creds = $connection->credentials ?? [];
        $expiresAt = isset($creds['expires_at']) ? Carbon::parse($creds['expires_at']) : null;

        if ($expiresAt !== null && $expiresAt->isAfter(now()->addSeconds(60))) {
            return; // still valid
        }

        if (empty($creds['refresh_token'])) {
            return; // nothing to refresh with — will fail on use and surface as invalid
        }

        $tokens = $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => $creds['refresh_token'],
            'client_id' => $this->cfg('client_id'),
        ]);

        $connection->update(['credentials' => $tokens, 'last_verified_at' => now()]);
    }

    /** POST the token endpoint and normalise the response into stored-credential shape. */
    private function requestTokens(array $body): array
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post((string) $this->cfg('token_url'), $body);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic OAuth token error: '.$response->status().' — '.$response->body());
        }

        $json = $response->json();

        return [
            'access_token' => $json['access_token'] ?? null,
            'refresh_token' => $json['refresh_token'] ?? ($body['refresh_token'] ?? null),
            'expires_at' => isset($json['expires_in'])
                ? now()->addSeconds((int) $json['expires_in'])->toIso8601String()
                : null,
            'scope' => $json['scope'] ?? ($this->cfg('scope')),
        ];
    }

    private function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Anthropic subscription login is not enabled.');
        }
    }

    private function cfg(string $key): mixed
    {
        return config('stride.ai.oauth.anthropic.'.$key);
    }
}
