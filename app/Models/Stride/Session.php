<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    /** Client times must carry their offset, so the local day of a run is known. */
    public const CLIENT_TIMESTAMP_RULE = 'regex:/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,6})?(?:Z|[+-]\\d{2}:\\d{2})$/';

    protected $table = 'stride_sessions';

    protected $fillable = [
        'user_id',
        'block_id',
        'routine_id',
        'client_id',
        'kind',
        'title',
        'status',
        'scheduled_date',
        'volume_kg',
        'duration_min',
        'rpe',
        'skip_reason',
        'mood',
        'skip_note',
        'notes',
        'started_at',
        'completed_at',
        'ended_early',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'volume_kg' => 'integer',
            'duration_min' => 'integer',
            'rpe' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'ended_early' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(SessionExercise::class)->orderBy('position');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
