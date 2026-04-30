<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum DrivetrainEnum: string
{
    use EnumHelper;

    case FWD = 'fwd';
    case RWD = 'rwd';
    case AWD = 'awd';
    case FOUR_WD = '4wd';
}
