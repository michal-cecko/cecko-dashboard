<?php

namespace App\Enums\Invoices;

use App\Traits\Common\EnumHelper;

enum RecurringIntervalEnum: string
{
    use EnumHelper;

    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
}
