<?php

namespace App\Providers;

use App\Services\Stride\Coach\AnthropicCoachProvider;
use App\Services\Stride\Coach\CoachProvider;
use App\Services\Stride\Coach\LocalCoachProvider;
use App\Services\Stride\Coach\OllamaCoachProvider;
use Illuminate\Support\ServiceProvider;

class StrideServiceProvider extends ServiceProvider
{
    /**
     * Bind the coach provider chosen in config/stride.php.
     *
     * Falls back to the keyless LocalCoachProvider when "anthropic" is selected
     * but no API key is configured — so local dev works out of the box and
     * upgrades to the real model automatically once ANTHROPIC_API_KEY is set.
     */
    public function register(): void
    {
        $this->app->bind(CoachProvider::class, function () {
            $driver = config('stride.coach.driver');
            $hasKey = ! empty(config('services.anthropic.api_key'));

            return match (true) {
                $driver === 'local', $driver === 'fake' => new LocalCoachProvider,
                $driver === 'ollama' => new OllamaCoachProvider,
                $driver === 'anthropic' && $hasKey => new AnthropicCoachProvider,
                default => new LocalCoachProvider,
            };
        });
    }
}
