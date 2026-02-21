<?php

namespace App\Enums;

use App\Traits\EnumHelper;

enum VatTypeEnum: string
{
    use EnumHelper;

    case STANDARD = 'standard';
    case ZERO_RATE = 'zero_rate';
    case REVERSE_CHARGE = 'reverse_charge';
}
