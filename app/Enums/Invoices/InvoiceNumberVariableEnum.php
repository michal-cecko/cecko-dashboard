<?php

namespace App\Enums\Invoices;

use App\Traits\Common\EnumHelper;

enum InvoiceNumberVariableEnum: string
{
    use EnumHelper;

    case YEAR = '{YEAR}';
    case YY = '{YY}';
    case MONTH = '{MONTH}';
    case SEQ = '{SEQ}';
}
