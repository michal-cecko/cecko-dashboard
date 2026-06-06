<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    protected $table = 'stride_blocks';

    protected $fillable = [
        'user_id',
        'name',
        'phase',
        'status',
        'weeks',
        'week_of',
        'starts_on',
        'ends_on',
        'summary',
        'accent',
        'stats',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'weeks' => 'integer',
            'week_of' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'stats' => 'array',
            'sort' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class)->orderBy('scheduled_date');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
