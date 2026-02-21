<?php

namespace App\Enums;

use App\Traits\EnumHelper;

enum PaymentMethodEnum: string
{
    use EnumHelper;

    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CARD = 'card';
    case PAYPAL = 'paypal';
    case CRYPTO = 'crypto';
    case OTHER = 'other';
}
