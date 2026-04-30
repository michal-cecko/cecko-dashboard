<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum BikeTireTypeEnum: string
{
    use EnumHelper;

    case CLINCHER = 'clincher';
    case TUBELESS = 'tubeless';
    case TUBULAR = 'tubular';
}
