<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum MotorcycleCoolingEnum: string
{
    use EnumHelper;

    case AIR = 'air';
    case OIL = 'oil';
    case LIQUID = 'liquid';
}
