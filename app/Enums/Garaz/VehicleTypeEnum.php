<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum VehicleTypeEnum: string
{
    use EnumHelper;

    case CAR = 'car';
    case MOTORCYCLE = 'motorcycle';
    case BICYCLE = 'bicycle';
}
