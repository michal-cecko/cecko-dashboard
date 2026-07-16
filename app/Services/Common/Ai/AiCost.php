<?php

namespace App\Services\Common\Ai;

class AiCost
{
    /**
     * USD cost estimate for a call — indexes the pricing map directly, NOT via
     * config("ai.pricing.{$model}"), because model ids contain dots (e.g.
     * "gemini-3.5-flash") which Laravel's dot-notation would misread as nested
     * keys and silently miss.
     */
    public static function usd(string $model, AiTokenUsage $usage): float
    {
        $pricing = config('ai.pricing');
        $rates = $pricing[$model] ?? $pricing['default'];

        return round((
            $usage->inputTokens * $rates['input']
            + $usage->outputTokens * $rates['output']
            + $usage->cacheCreationTokens * $rates['cache_write']
            + $usage->cacheReadTokens * $rates['cache_read']
        ) / 1_000_000, 6);
    }
}
