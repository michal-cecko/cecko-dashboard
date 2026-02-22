<?php

namespace App\Filament\Invoices\Resources\InvoicePayments\Pages;

use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Resources\InvoicePayments\InvoicePaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoicePayments extends ListRecords
{
    use HasCompanyBreadcrumb;

    protected static string $resource = InvoicePaymentResource::class;
}
