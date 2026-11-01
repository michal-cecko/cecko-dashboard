<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An interval timer the user builds for a class. `steps` is a tree of work,
 * rest and loop steps (see RoutineSteps); total_seconds and step_count are
 * stored so the list doesn't have to walk every tree.
 */
class Routine extends Model
{
    public const KINDS = ['yoga', 'pilates', 'cardio', 'strength', 'other'];

    protected $table = 'stride_routines';

    protected $fillable = [
        'user_id',
        'title',
        'kind',
        'steps',
        'total_seconds',
        'step_count',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'total_seconds' => 'integer',
            'step_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
