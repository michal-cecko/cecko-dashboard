<?php

namespace App\Enums\Invoices;

use App\Traits\Common\EnumHelper;

enum VatTypeEnum: string
{
    use EnumHelper;

    case STANDARD = 'standard';
    case ZERO_RATE = 'zero_rate';
    case REVERSE_CHARGE = 'reverse_charge';
}
