<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum VehicleDocumentTypeEnum: string
{
    use EnumHelper;

    case STK = 'stk';
    case EK = 'ek';
    case INSURANCE_PZP = 'insurance_pzp';
    case INSURANCE_HAVARIJKA = 'insurance_havarijka';
    case REGISTRATION = 'registration';
    case SERVICE_BOOK = 'service_book';
    case INVOICE_RECEIPT = 'invoice_receipt';
    case OTHER = 'other';

    public function tracksExpiry(): bool
    {
        return match ($this) {
            self::STK,
            self::EK,
            self::INSURANCE_PZP,
            self::INSURANCE_HAVARIJKA => true,
            default => false,
        };
    }
}
