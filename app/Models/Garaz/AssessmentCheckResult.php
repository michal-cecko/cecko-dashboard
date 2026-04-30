<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\CheckOutcomeEnum;
use App\Enums\Garaz\ConcernCheckInputEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentCheckResult extends Model
{
    protected $fillable = [
        'concern_assessment_id',
        'concern_check_id',
        'order',
        'name',
        'input_type',
        'user_input',
        'user_notes',
        'ai_assessment',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'input_type' => ConcernCheckInputEnum::class,
            'user_input' => 'array',
            'ai_assessment' => 'array',
            'outcome' => CheckOutcomeEnum::class,
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ConcernAssessment::class, 'concern_assessment_id');
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(ConcernCheck::class, 'concern_check_id');
    }
}
