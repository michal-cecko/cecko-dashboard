<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum OdometerSourceEnum: string
{
    use EnumHelper;

    case INITIAL = 'initial';
    case MANUAL = 'manual';
    case SERVICE = 'service';
    case DIY = 'diy';
}
