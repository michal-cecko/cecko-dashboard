<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrideProfile extends Model
{
    protected $fillable = [
        'user_id',
        'height_cm',
        'weight_kg',
        'goal_weight_kg',
        'body_fat_pct',
        'persona_key',
        'units',
        'streak_days',
        'preferences',
        'daily_pokes',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'integer',
            'weight_kg' => 'float',
            'goal_weight_kg' => 'float',
            'body_fat_pct' => 'float',
            'streak_days' => 'integer',
            'preferences' => 'array',
            'daily_pokes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
