<?php

namespace App\Models\Garaz;

use App\Enums\Garaz\FuelTypeEnum;
use App\Enums\Garaz\MotorcycleCoolingEnum;
use App\Enums\Garaz\MotorcycleEngineLayoutEnum;
use App\Enums\Garaz\MotorcycleFinalDriveEnum;
use App\Enums\Garaz\TransmissionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotorcycleSpec extends Model
{
    protected $fillable = [
        'vehicle_id',
        'engine_layout',
        'displacement_ccm',
        'power_kw',
        'cooling',
        'fuel_type',
        'transmission',
        'gear_count',
        'final_drive',
        'oil_spec',
        'tire_front',
        'tire_rear',
    ];

    protected function casts(): array
    {
        return [
            'engine_layout' => MotorcycleEngineLayoutEnum::class,
            'cooling' => MotorcycleCoolingEnum::class,
            'fuel_type' => FuelTypeEnum::class,
            'transmission' => TransmissionEnum::class,
            'final_drive' => MotorcycleFinalDriveEnum::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
