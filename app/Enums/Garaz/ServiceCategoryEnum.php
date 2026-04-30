<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum ServiceCategoryEnum: string
{
    use EnumHelper;

    case OIL_CHANGE = 'oil_change';
    case OIL_FILTER = 'oil_filter';
    case AIR_FILTER = 'air_filter';
    case CABIN_FILTER = 'cabin_filter';
    case FUEL_FILTER = 'fuel_filter';
    case BRAKES = 'brakes';
    case TIRES = 'tires';
    case TIMING = 'timing';
    case CLUTCH = 'clutch';
    case BATTERY = 'battery';
    case COOLANT = 'coolant';
    case REPAIR = 'repair';
    case INSPECTION = 'inspection';
    case STK = 'stk';
    case EK = 'ek';
    case OTHER = 'other';
}
