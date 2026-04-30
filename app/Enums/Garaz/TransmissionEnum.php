<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum TransmissionEnum: string
{
    use EnumHelper;

    case MANUAL = 'manual';
    case AUTOMATIC = 'automatic';
    case DCT = 'dct';
    case CVT = 'cvt';
}
