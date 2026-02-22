<?php

namespace App\Enums\Invoices;

use App\Traits\Common\EnumHelper;

enum InvoiceStatusEnum: string
{
    use EnumHelper;

    case NEW = 'new';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case AFTER_DUE = 'after_due';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
