<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum AssessmentVerdictEnum: string
{
    use EnumHelper;

    case OPEN = 'open';
    case CLEAR = 'clear';
    case SHOP = 'shop';
    case MONITOR = 'monitor';
}
