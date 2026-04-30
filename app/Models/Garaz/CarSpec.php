<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\DrivetrainEnum;
use App\Enums\Garaz\EmissionStandardEnum;
use App\Enums\Garaz\FuelTypeEnum;
use App\Enums\Garaz\TransmissionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarSpec extends Model
{
    protected $fillable = [
        'vehicle_id',
        'fuel_type',
        'engine_code',
        'displacement_l',
        'power_kw',
        'transmission',
        'gear_count',
        'drivetrain',
        'oil_spec',
        'oil_viscosity',
        'oil_capacity_l',
        'fuel_tank_l',
        'tire_front',
        'tire_rear',
        'emission_standard',
    ];

    protected function casts(): array
    {
        return [
            'fuel_type' => FuelTypeEnum::class,
            'transmission' => TransmissionEnum::class,
            'drivetrain' => DrivetrainEnum::class,
            'emission_standard' => EmissionStandardEnum::class,
            'displacement_l' => 'decimal:1',
            'oil_capacity_l' => 'decimal:1',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
