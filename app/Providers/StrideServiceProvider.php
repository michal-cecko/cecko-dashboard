<?php

namespace App\Providers;

use App\Services\Common\Ai\AiProvider;
use App\Services\Common\Ai\AiProviderResolver;
use App\Services\Stride\Coach\AnthropicCoachProvider;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\GeminiCoachProvider;
use App\Services\Stride\Coach\LocalCoachProvider;
use App\Services\Stride\Coach\OllamaCoachProvider;
use App\Services\Stride\Coach\OpenAiCoachProvider;
use Illuminate\Support\ServiceProvider;

class StrideServiceProvider extends ServiceProvider
{
    /**
     * Wire the AI providers.
     *
     * Per-user resolution goes through AiProviderResolver (services inject it and
     * resolve a provider per user — BYOK or the free tier). The CoachProvider /
     * AiProvider bindings are the app-default (free-tier) provider, chosen from
     * config with the graceful "no key → local stub" fallback the app relied on.
     * The resolver's free path resolves this same binding, so tests that swap
     * CoachProvider for a fake keep working unchanged.
     */
    public function register(): void
    {
        $this->app->bind(CoachProvider::class, function () {
            $driver = config('stride.coach.driver');
            $hasAnthropicKey = ! empty(config('services.anthropic.api_key'));
            $hasGeminiKey = ! empty(config('services.gemini.api_key'));
            $hasOpenAiKey = ! empty(config('services.openai.api_key'));

            return match (true) {
                $driver === 'local', $driver === 'fake' => new LocalCoachProvider,
                $driver === 'ollama' => new OllamaCoachProvider,
                $driver === 'anthropic' && $hasAnthropicKey => new AnthropicCoachProvider,
                $driver === 'gemini' && $hasGeminiKey => new GeminiCoachProvider,
                $driver === 'openai' && $hasOpenAiKey => new OpenAiCoachProvider,
                default => new LocalCoachProvider,
            };
        });

        $this->app->bind(AiProvider::class, fn ($app) => $app->make(CoachProvider::class));

        $this->app->singleton(AiProviderResolver::class);
    }
}
