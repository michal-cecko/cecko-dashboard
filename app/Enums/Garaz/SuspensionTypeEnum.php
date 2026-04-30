<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum SuspensionTypeEnum: string
{
    use EnumHelper;

    case RIGID = 'rigid';
    case HARDTAIL = 'hardtail';
    case FULL = 'full';
}
