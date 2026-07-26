<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One day's "how do you feel" check-in: energy 1-5 + an optional note. */
class Checkin extends Model
{
    protected $table = 'stride_checkins';

    protected $fillable = ['user_id', 'checked_on', 'energy', 'note'];

    protected function casts(): array
    {
        return ['checked_on' => 'date', 'energy' => 'integer'];
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
