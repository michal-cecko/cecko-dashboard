<?php

namespace App\Enums;

use App\Traits\EnumHelper;

enum LocaleEnum: string
{
    use EnumHelper;

    case SK = 'sk';
    case CS = 'cs';
    case EN = 'en';
}
