<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum WheelSizeEnum: string
{
    use EnumHelper;

    case INCH_24 = '24';
    case INCH_26 = '26';
    case INCH_27_5 = '27.5';
    case INCH_28 = '28';
    case INCH_29 = '29';
    case SIZE_700C = '700c';
    case SIZE_650B = '650b';
}
