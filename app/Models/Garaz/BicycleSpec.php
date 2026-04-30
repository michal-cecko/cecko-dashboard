<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\BikeCategoryEnum;
use App\Enums\Garaz\BikeTireTypeEnum;
use App\Enums\Garaz\BrakeTypeEnum;
use App\Enums\Garaz\FrameMaterialEnum;
use App\Enums\Garaz\SuspensionTypeEnum;
use App\Enums\Garaz\WheelSizeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BicycleSpec extends Model
{
    protected $fillable = [
        'vehicle_id',
        'bike_category',
        'frame_material',
        'frame_size',
        'wheel_size',
        'drivetrain_brand',
        'drivetrain_speeds',
        'front_brake_type',
        'rear_brake_type',
        'suspension_type',
        'fork_travel_mm',
        'rear_travel_mm',
        'has_dropper_post',
        'dropper_brand_model',
        'tire_type',
        'tire_width_mm',
        'tire_pressure_bar',
        'is_electric',
        'motor_brand',
        'motor_model',
        'battery_wh',
        'range_km_estimated',
        'ride_modes',
    ];

    protected function casts(): array
    {
        return [
            'bike_category' => BikeCategoryEnum::class,
            'frame_material' => FrameMaterialEnum::class,
            'wheel_size' => WheelSizeEnum::class,
            'front_brake_type' => BrakeTypeEnum::class,
            'rear_brake_type' => BrakeTypeEnum::class,
            'suspension_type' => SuspensionTypeEnum::class,
            'tire_type' => BikeTireTypeEnum::class,
            'has_dropper_post' => 'boolean',
            'is_electric' => 'boolean',
            'tire_pressure_bar' => 'decimal:1',
            'ride_modes' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
