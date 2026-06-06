<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    protected $table = 'stride_goals';

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'progress',
        'current_label',
        'target_label',
        'deadline',
        'color',
        'is_achieved',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'float',
            'deadline' => 'date',
            'is_achieved' => 'boolean',
            'sort' => 'integer',
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
