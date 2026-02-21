<?php

namespace App\Enums;

use App\Traits\EnumHelper;

enum InvoiceNumberVariableEnum: string
{
    use EnumHelper;

    case YEAR = '{YEAR}';
    case YY = '{YY}';
    case MONTH = '{MONTH}';
    case SEQ = '{SEQ}';
}
