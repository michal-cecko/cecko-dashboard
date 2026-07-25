<?php

namespace App\Services\Common\Ai;

use App\Models\Common\AiConnection;
use App\Models\Common\User;
use App\Services\Common\Ai\Auth\AnthropicOAuthService;
use App\Services\Stride\Coach\AnthropicCoachProvider;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\GeminiCoachProvider;
use App\Services\Stride\Coach\OpenAiCoachProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves which AI provider + model to use for a given user. This is the single
 * switch point that replaced the deployment-wide provider binding: each user can
 * bring their own key/model (BYOK); anyone who hasn't connected one runs on the
 * app-subsidised free tier (the legacy config-driven coach provider).
 *
 * Call sites inject this resolver (not a provider) so cron paths can resolve per
 * target user without relying on the request's authenticated user.
 */
class AiProviderResolver
{
    public function __construct(private readonly AnthropicOAuthService $oauth) {}

    /** Resolve the effective provider + model for one user (optionally forcing a model). */
    public function for(User $user, ?string $override = null): ResolvedAi
    {
        $connection = AiConnection::firstWhere('user_id', $user->id);

        if ($connection === null
            || $connection->provider === 'free'
            || $connection->status === 'invalid'
            || ! $connection->isByok()) {
            return ResolvedAi::free($this->defaultProvider());
        }

        if ($connection->auth_type === 'oauth') {
            try {
                $this->oauth->ensureFresh($connection);
                $connection->refresh();
            } catch (Throwable $e) {
                report($e);

                return ResolvedAi::free($this->defaultProvider());
            }
        }

        $provider = $this->buildProvider($connection->provider, $connection->toCredentials());
        if ($provider === null) {
            return ResolvedAi::free($this->defaultProvider());
        }

        return new ResolvedAi(
            provider: $provider,
            isByok: true,
            providerKey: $connection->provider,
            connectionModel: $connection->model,
            override: $override,
        );
    }

    /**
     * The free / app-default provider — the config-driven coach binding
     * (StrideServiceProvider). Resolved from the container so a test that swaps
     * CoachProvider for a fake is honoured here too.
     */
    public function defaultProvider(): CoachProvider
    {
        return app(CoachProvider::class);
    }

    /** Construct a credentialed driver for a BYOK provider, or null if unknown. */
    private function buildProvider(string $provider, AiCredentials $creds): ?CoachProvider
    {
        return match ($provider) {
            'anthropic' => new AnthropicCoachProvider($creds),
            'gemini' => new GeminiCoachProvider($creds),
            'openai' => new OpenAiCoachProvider($creds),
            default => null,
        };
    }

    /**
     * Validation ping: build a provider from candidate (not-yet-saved) API-key
     * credentials and fire the cheapest possible call against the chosen model.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function probe(string $provider, AiCredentials $creds, string $model): array
    {
        $driver = $this->buildProvider($provider, $creds);
        if ($driver === null) {
            return ['ok' => false, 'error' => 'Unknown provider.'];
        }

        try {
            $driver->chat(new AiTurn(
                model: $model,
                systemBlocks: [['text' => 'Reply with OK.', 'cache' => false]],
                messages: [['role' => 'user', 'content' => 'ping']],
                maxTokens: 16,
                purpose: 'probe',
            ));

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => Str::limit($e->getMessage(), 240)];
        }
    }
}
