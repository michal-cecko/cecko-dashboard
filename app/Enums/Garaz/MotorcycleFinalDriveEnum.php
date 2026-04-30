<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum MotorcycleFinalDriveEnum: string
{
    use EnumHelper;

    case CHAIN = 'chain';
    case BELT = 'belt';
    case SHAFT = 'shaft';
}
