<?php

namespace App\Providers;

use App\Services\Stride\Coach\AnthropicCoachProvider;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\GeminiCoachProvider;
use App\Services\Stride\Coach\LocalCoachProvider;
use App\Services\Stride\Coach\OllamaCoachProvider;
use Illuminate\Support\ServiceProvider;

class StrideServiceProvider extends ServiceProvider
{
    /**
     * Bind the coach provider chosen in config/stride.php.
     *
     * Falls back to the keyless LocalCoachProvider when a cloud driver
     * (anthropic/gemini) is selected but its API key is missing — so local dev
     * works out of the box and upgrades to the real model automatically once
     * the key is set.
     */
    public function register(): void
    {
        $this->app->bind(CoachProvider::class, function () {
            $driver = config('stride.coach.driver');
            $hasAnthropicKey = ! empty(config('services.anthropic.api_key'));
            $hasGeminiKey = ! empty(config('services.gemini.api_key'));

            return match (true) {
                $driver === 'local', $driver === 'fake' => new LocalCoachProvider,
                $driver === 'ollama' => new OllamaCoachProvider,
                $driver === 'anthropic' && $hasAnthropicKey => new AnthropicCoachProvider,
                $driver === 'gemini' && $hasGeminiKey => new GeminiCoachProvider,
                default => new LocalCoachProvider,
            };
        });
    }
}
