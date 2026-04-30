<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\ConcernCheckInputEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcernCheck extends Model
{
    protected $fillable = [
        'maintenance_concern_id',
        'order',
        'name',
        'instruction',
        'input_type',
        'input_options',
        'ai_assessment_prompt',
        'pass_criteria',
        'fail_criteria',
        'uncertain_criteria',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'input_type' => ConcernCheckInputEnum::class,
            'input_options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(MaintenanceConcern::class, 'maintenance_concern_id');
    }
}
