<?php

namespace App\Enums\Tickets;

use App\Traits\Common\EnumHelper;

enum PlanBillingEnum: string
{
    use EnumHelper;

    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function months(): int
    {
        return $this === self::YEARLY ? 12 : 1;
    }
}
