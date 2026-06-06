<?php

namespace App\Models\Stride;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSet extends Model
{
    protected $table = 'stride_sets';

    protected $fillable = [
        'session_exercise_id',
        'kind',
        'reps',
        'kg',
        'rest_sec',
        'position',
        'is_done',
        'actual_reps',
        'actual_kg',
    ];

    protected function casts(): array
    {
        return [
            'reps' => 'integer',
            'kg' => 'float',
            'rest_sec' => 'integer',
            'position' => 'integer',
            'is_done' => 'boolean',
            'actual_reps' => 'integer',
            'actual_kg' => 'float',
        ];
    }

    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(SessionExercise::class, 'session_exercise_id');
    }
}
