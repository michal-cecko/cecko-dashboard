<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\OdometerSourceEnum;
use App\Models\Common\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdometerReading extends Model
{
    protected $fillable = [
        'vehicle_id',
        'reading_km',
        'recorded_at',
        'source',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'source' => OdometerSourceEnum::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (OdometerReading $reading): void {
            $vehicle = $reading->vehicle;

            if ($vehicle === null) {
                return;
            }

            $latest = $vehicle->odometerReadings()->orderByDesc('recorded_at')->first();

            if ($latest === null) {
                return;
            }

            $vehicle->forceFill([
                'current_odometer_km' => $latest->reading_km,
                'current_odometer_at' => $latest->recorded_at,
            ])->saveQuietly();
        });

        static::deleted(function (OdometerReading $reading): void {
            $vehicle = $reading->vehicle;

            if ($vehicle === null) {
                return;
            }

            $latest = $vehicle->odometerReadings()->orderByDesc('recorded_at')->first();

            $vehicle->forceFill([
                'current_odometer_km' => $latest?->reading_km,
                'current_odometer_at' => $latest?->recorded_at,
            ])->saveQuietly();
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
