<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Spot extends Model
{
    protected $table = 'stride_spots';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'size',
        'blurb',
        'is_official',
        'is_verified',
        'photo_path',
        'equipment',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'is_verified' => 'boolean',
            'equipment' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Curated directory entries (no owner). */
    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    /** Spots a given user owns. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
