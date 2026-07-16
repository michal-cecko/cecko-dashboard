<?php

namespace App\Services\Common\Ai;

use Illuminate\Database\Eloquent\Model;

/**
 * Hourly bucketing for AI usage logs. Instead of one row per model call,
 * repeated calls with the same key (user, purpose, provider, model, …) merge
 * into the bucket row created within the last hour: token counts and cost are
 * summed, `calls` is incremented, and latency becomes a running average. A
 * bucket naturally closes one hour after its first call (created_at anchors
 * the window); updated_at shows the bucket's most recent call.
 */
class AiUsageBucket
{
    /**
     * @param  class-string<Model>  $modelClass  the panel's AI usage model
     * @param  array<string, mixed>  $keys  bucket identity (must include user_id)
     * @param  array{input_tokens: int, output_tokens: int, cache_creation_tokens: int, cache_read_tokens: int, latency_ms: ?int, cost_usd: float}  $usage
     */
    public static function record(string $modelClass, array $keys, array $usage): Model
    {
        $bucket = $modelClass::query()
            ->where($keys)
            ->where('created_at', '>=', now()->subHour())
            ->latest('created_at')
            ->first();

        if ($bucket === null) {
            return $modelClass::create([...$keys, ...$usage, 'calls' => 1]);
        }

        $calls = (int) $bucket->calls;

        $bucket->update([
            'input_tokens' => $bucket->input_tokens + $usage['input_tokens'],
            'output_tokens' => $bucket->output_tokens + $usage['output_tokens'],
            'cache_creation_tokens' => $bucket->cache_creation_tokens + $usage['cache_creation_tokens'],
            'cache_read_tokens' => $bucket->cache_read_tokens + $usage['cache_read_tokens'],
            'latency_ms' => $usage['latency_ms'] !== null
                ? (int) round((($bucket->latency_ms ?? 0) * $calls + $usage['latency_ms']) / ($calls + 1))
                : $bucket->latency_ms,
            'cost_usd' => round((float) $bucket->cost_usd + $usage['cost_usd'], 6),
            'calls' => $calls + 1,
        ]);

        return $bucket;
    }
}
