<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum BrakeTypeEnum: string
{
    use EnumHelper;

    case RIM = 'rim';
    case DISC_MECH = 'disc_mech';
    case DISC_HYDRAULIC = 'disc_hydraulic';
}
