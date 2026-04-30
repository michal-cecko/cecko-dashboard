<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\VehicleTypeEnum;
use App\Models\Common\User;
use Database\Factories\Garaz\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'type',
        'nickname',
        'make',
        'model',
        'trim',
        'year_of_manufacture',
        'first_registration_date',
        'vin_or_serial',
        'license_plate',
        'color',
        'purchase_date',
        'purchase_price_eur',
        'purchase_mileage_km',
        'current_odometer_km',
        'current_odometer_at',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => VehicleTypeEnum::class,
            'first_registration_date' => 'date',
            'purchase_date' => 'date',
            'purchase_price_eur' => 'decimal:2',
            'current_odometer_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
        $this->addMediaCollection('documents');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function carSpec(): HasOne
    {
        return $this->hasOne(CarSpec::class);
    }

    public function motorcycleSpec(): HasOne
    {
        return $this->hasOne(MotorcycleSpec::class);
    }

    public function bicycleSpec(): HasOne
    {
        return $this->hasOne(BicycleSpec::class);
    }

    public function odometerReadings(): HasMany
    {
        return $this->hasMany(OdometerReading::class)->orderByDesc('recorded_at');
    }

    public function spec(): ?Model
    {
        return match ($this->type) {
            VehicleTypeEnum::CAR => $this->carSpec,
            VehicleTypeEnum::MOTORCYCLE => $this->motorcycleSpec,
            VehicleTypeEnum::BICYCLE => $this->bicycleSpec,
            default => null,
        };
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
