<?php

namespace App\Enums\Common;

use App\Traits\Common\EnumHelper;

enum CountryEnum: string
{
    use EnumHelper;

    case SK = 'SK';
    case CZ = 'CZ';

    public function defaultCurrency(): CurrencyEnum
    {
        return match ($this) {
            self::SK => CurrencyEnum::EUR,
            self::CZ => CurrencyEnum::CZK,
        };
    }
}
