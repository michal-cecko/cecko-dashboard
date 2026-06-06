<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Injury extends Model
{
    protected $table = 'stride_injuries';

    protected $fillable = [
        'user_id',
        'body_part',
        'label',
        'severity',
        'status',
        'since',
        'note',
        'avoid',
        'safe',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'since' => 'date',
            'avoid' => 'array',
            'safe' => 'array',
            'sort' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(InjuryJournalEntry::class)->orderByDesc('entry_date');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** Injuries the coach must currently program around. */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'monitoring']);
    }
}
