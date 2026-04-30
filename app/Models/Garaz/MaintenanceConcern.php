<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\ConcernTriggerEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceConcern extends Model
{
    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'trigger_config',
        'vehicle_type_match',
        'engine_code_match',
        'bike_category_match',
        'shop_diagnostic_cost_min_eur',
        'shop_diagnostic_cost_max_eur',
        'self_check_minutes',
        'recheck_after_days',
        'recheck_after_km',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => ConcernTriggerEnum::class,
            'trigger_config' => 'array',
            'shop_diagnostic_cost_min_eur' => 'decimal:2',
            'shop_diagnostic_cost_max_eur' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(ConcernCheck::class)->orderBy('order');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ConcernAssessment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeApplicableTo(Builder $query, Vehicle $vehicle): Builder
    {
        return $query->active()
            ->where(function (Builder $q) use ($vehicle): void {
                $q->whereNull('vehicle_type_match')
                    ->orWhere('vehicle_type_match', $vehicle->type?->value);
            })
            ->where(function (Builder $q) use ($vehicle): void {
                $engineCode = $vehicle->carSpec?->engine_code ?? $vehicle->motorcycleSpec?->engine_layout?->value;
                $q->whereNull('engine_code_match')
                    ->when($engineCode !== null, fn ($qq) => $qq->orWhere('engine_code_match', $engineCode));
            })
            ->where(function (Builder $q) use ($vehicle): void {
                $bikeCategory = $vehicle->bicycleSpec?->bike_category?->value;
                $q->whereNull('bike_category_match')
                    ->when($bikeCategory !== null, fn ($qq) => $qq->orWhere('bike_category_match', $bikeCategory));
            });
    }

    public function shopCostRange(): ?string
    {
        if ($this->shop_diagnostic_cost_min_eur === null && $this->shop_diagnostic_cost_max_eur === null) {
            return null;
        }

        $min = $this->shop_diagnostic_cost_min_eur;
        $max = $this->shop_diagnostic_cost_max_eur;

        if ($min !== null && $max !== null) {
            return number_format((float) $min, 0, ',', ' ').'–'.number_format((float) $max, 0, ',', ' ').' €';
        }

        return number_format((float) ($min ?? $max), 0, ',', ' ').' €';
    }
}
