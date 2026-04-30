<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum FuelTypeEnum: string
{
    use EnumHelper;

    case PETROL = 'petrol';
    case DIESEL = 'diesel';
    case HYBRID = 'hybrid';
    case PHEV = 'phev';
    case EV = 'ev';
    case LPG = 'lpg';
    case CNG = 'cng';
}
