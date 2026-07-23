<?php

namespace App\Models\Stride;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One logged metric of a performed set (reps, seconds, weight_kg, band_kg, …).
 * Keys are App\Enums\Stride\SetMetric values; unique per set + metric.
 */
class SetMetricValue extends Model
{
    protected $table = 'stride_set_metrics';

    protected $fillable = [
        'set_id',
        'metric',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(ExerciseSet::class, 'set_id');
    }
}
