<?php

namespace App\Models\Support;

use App\Enums\Support\SupportPlanEnum;
use Database\Factories\Support\SupportPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportPeriod extends Model
{
    /** @use HasFactory<SupportPeriodFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = ['granted_minutes' => 0, 'used_minutes' => 0, 'granted_credit_minor' => 0, 'used_credit_minor' => 0, 'granted_call_minutes' => 0, 'used_call_minutes' => 0, 'granted_planning_minutes' => 0, 'used_planning_minutes' => 0];

    protected function casts(): array
    {
        return [
            'plan' => SupportPlanEnum::class,
            'pricing_snapshot' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SupportProject::class, 'project_id');
    }
}
