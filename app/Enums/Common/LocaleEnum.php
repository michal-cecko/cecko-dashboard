<?php

namespace App\Enums\Common;

use App\Traits\Common\EnumHelper;

enum LocaleEnum: string
{
    use EnumHelper;

    case SK = 'sk';
    case CZ = 'cs';
    case EN = 'en';
}
