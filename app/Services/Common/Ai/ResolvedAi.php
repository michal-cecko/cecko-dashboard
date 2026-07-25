<?php

namespace App\Services\Common\Ai;

/**
 * The outcome of resolving a user's AI connection: a ready-to-call driver plus
 * the model to use per purpose. Call sites ask for a model by purpose
 * ('chat' | 'summary' | 'generate') instead of reading config directly, so a
 * BYOK user's chosen model and the free tier's app defaults both flow through
 * one place.
 */
readonly class ResolvedAi
{
    /**
     * @param  string  $providerKey  the connection provider: anthropic|gemini|openai|free
     * @param  string|null  $connectionModel  the user's chosen model (null → per-purpose default)
     * @param  string|null  $override  a per-conversation/per-request model override
     */
    public function __construct(
        public AiProvider $provider,
        public bool $isByok,
        public string $providerKey,
        public ?string $connectionModel = null,
        public ?string $override = null,
    ) {}

    /** The free (app-subsidised) tier running on the app's default coach provider. */
    public static function free(AiProvider $provider): self
    {
        return new self($provider, isByok: false, providerKey: 'free');
    }

    /** The driver name used for usage logging (anthropic|gemini|openai|local|ollama). */
    public function driverName(): string
    {
        return $this->provider->name();
    }

    /**
     * Resolve the model id for a purpose.
     *
     * Free tier → the app's coach config models (unchanged legacy behaviour).
     * BYOK → the user's explicit pick (override, else connection model) for every
     * purpose; when they picked no model, the provider catalog's per-purpose
     * default. An override that doesn't match the provider family is ignored.
     */
    public function model(string $purpose = 'chat'): string
    {
        if (! $this->isByok) {
            return match ($purpose) {
                'summary' => (string) config('stride.coach.summary_model', config('stride.coach.model')),
                'generate' => (string) config('stride.coach.generate_model', config('stride.coach.model')),
                default => (string) config('stride.coach.model'),
            };
        }

        $override = ($this->override !== null && self::familyMatches($this->providerKey, $this->override))
            ? $this->override
            : null;

        $chosen = $override ?: $this->connectionModel;
        if ($chosen) {
            return $chosen;
        }

        $catalog = (array) config('stride.ai.providers.'.$this->providerKey, []);

        return match ($purpose) {
            'generate' => (string) ($catalog['generate'] ?? $catalog['default'] ?? ''),
            default => (string) ($catalog['default'] ?? ''),
        };
    }

    /** Whether a model id belongs to a provider's family (guards cross-provider overrides). */
    public static function familyMatches(string $providerKey, string $model): bool
    {
        return match ($providerKey) {
            'anthropic' => str_starts_with($model, 'claude'),
            'gemini' => str_starts_with($model, 'gemini'),
            'openai' => str_starts_with($model, 'gpt') || str_starts_with($model, 'o'),
            default => false,
        };
    }
}
