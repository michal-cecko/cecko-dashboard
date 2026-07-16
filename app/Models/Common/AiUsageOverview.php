<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only model over the ai_usage_overview database view — a UNION of the
 * per-panel AI usage tables (stride_ai_usage, garaz_ai_usage). Rows carry a
 * synthetic text id ('stride-1', 'garaz-1'), so the key is a non-incrementing
 * string. Never write through this model.
 */
class AiUsageOverview extends Model
{
    protected $table = 'ai_usage_overview';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_creation_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'latency_ms' => 'integer',
            'cost_usd' => 'float',
            'calls' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
