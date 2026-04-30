<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum EmissionStandardEnum: string
{
    use EnumHelper;

    case EURO4 = 'euro4';
    case EURO5 = 'euro5';
    case EURO6 = 'euro6';
    case EURO6D = 'euro6d';
}
