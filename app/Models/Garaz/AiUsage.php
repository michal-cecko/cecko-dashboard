<?php

namespace App\Models\Garaz;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    protected $table = 'garaz_ai_usage';

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'provider',
        'model',
        'purpose',
        'input_tokens',
        'output_tokens',
        'cache_creation_tokens',
        'cache_read_tokens',
        'latency_ms',
        'cost_usd',
        'calls',
    ];

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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
