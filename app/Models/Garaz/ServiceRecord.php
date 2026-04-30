<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\OdometerSourceEnum;
use App\Enums\Garaz\ServiceCategoryEnum;
use App\Enums\Garaz\ServiceSourceEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ServiceRecord extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'vehicle_id',
        'performed_at',
        'mileage_km',
        'category',
        'source',
        'shop_name',
        'technician',
        'work_summary',
        'parts',
        'labor_hours',
        'parts_cost_eur',
        'labor_cost_eur',
        'total_eur',
        'confidence_flags',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'category' => ServiceCategoryEnum::class,
            'source' => ServiceSourceEnum::class,
            'parts' => 'array',
            'labor_hours' => 'decimal:2',
            'parts_cost_eur' => 'decimal:2',
            'labor_cost_eur' => 'decimal:2',
            'total_eur' => 'decimal:2',
            'confidence_flags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ServiceRecord $record): void {
            if ($record->mileage_km === null) {
                return;
            }

            OdometerReading::create([
                'vehicle_id' => $record->vehicle_id,
                'reading_km' => $record->mileage_km,
                'recorded_at' => $record->performed_at,
                'source' => $record->source === ServiceSourceEnum::DIY ? OdometerSourceEnum::DIY : OdometerSourceEnum::SERVICE,
                'notes' => 'Auto z servisného záznamu: '.($record->category?->translation() ?? '—'),
            ]);
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeForCategory(Builder $query, ServiceCategoryEnum $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeBySource(Builder $query, ServiceSourceEnum $source): Builder
    {
        return $query->where('source', $source);
    }
}
