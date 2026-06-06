<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightEntry extends Model
{
    protected $table = 'stride_weight_entries';

    protected $fillable = [
        'user_id',
        'recorded_on',
        'kg',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'kg' => 'float',
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
