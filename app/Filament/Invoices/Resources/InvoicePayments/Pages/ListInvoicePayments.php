<?php

namespace App\Filament\Invoices\Resources\InvoicePayments\Pages;

use App\Filament\Invoices\Resources\InvoicePayments\InvoicePaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoicePayments extends ListRecords
{
    protected static string $resource = InvoicePaymentResource::class;
}
