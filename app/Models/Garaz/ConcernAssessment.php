<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\AssessmentVerdictEnum;
use App\Enums\Garaz\CheckOutcomeEnum;
use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ConcernAssessment extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'vehicle_id',
        'maintenance_concern_id',
        'opened_by_user_id',
        'opened_at',
        'closed_at',
        'verdict',
        'verdict_summary',
        'savings_eur',
        'next_due_at',
        'next_due_km',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'verdict' => AssessmentVerdictEnum::class,
            'savings_eur' => 'decimal:2',
            'next_due_at' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('briefings');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(MaintenanceConcern::class, 'maintenance_concern_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentCheckResult::class)->orderBy('order');
    }

    public function isOpen(): bool
    {
        return $this->verdict === AssessmentVerdictEnum::OPEN;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('verdict', AssessmentVerdictEnum::OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNot('verdict', AssessmentVerdictEnum::OPEN);
    }

    public function computeVerdictFromResults(): AssessmentVerdictEnum
    {
        $outcomes = $this->results()->pluck('outcome');

        if ($outcomes->contains(CheckOutcomeEnum::FAIL)) {
            return AssessmentVerdictEnum::SHOP;
        }

        if ($outcomes->contains(CheckOutcomeEnum::UNCERTAIN)) {
            return AssessmentVerdictEnum::MONITOR;
        }

        if ($outcomes->every(fn ($o) => in_array($o, [CheckOutcomeEnum::PASS, CheckOutcomeEnum::SKIPPED]))) {
            return AssessmentVerdictEnum::CLEAR;
        }

        return AssessmentVerdictEnum::OPEN;
    }
}
