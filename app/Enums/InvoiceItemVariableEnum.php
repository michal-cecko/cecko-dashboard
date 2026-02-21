<?php

namespace App\Enums;

use App\Traits\EnumHelper;

enum InvoiceItemVariableEnum: string
{
    use EnumHelper;

    case MONTH = '{MONTH}';
    case MONTH_NUM = '{MONTH_NUM}';
    case YEAR = '{YEAR}';
    case PERIOD = '{PERIOD}';
    case PREV_MONTH = '{PREV_MONTH}';
    case QUARTER = '{QUARTER}';
    case QUARTER_PERIOD = '{QUARTER_PERIOD}';
    case DATE_RANGE = '{DATE_RANGE}';
}
