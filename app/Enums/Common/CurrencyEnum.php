<?php

namespace App\Enums\Common;

use App\Traits\Common\EnumHelper;

enum CurrencyEnum: string
{
    use EnumHelper;

    case EUR = 'EUR';
    case CZK = 'CZK';

    public function symbol(): string
    {
        return match ($this) {
            self::EUR => '€',
            self::CZK => 'Kč',
        };
    }

    public function formatted(float|string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' '.$this->symbol();
    }
}
