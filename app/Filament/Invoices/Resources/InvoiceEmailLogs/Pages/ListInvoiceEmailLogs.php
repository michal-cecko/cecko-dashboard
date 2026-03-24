<?php

namespace App\Filament\Invoices\Resources\InvoiceEmailLogs\Pages;

use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Resources\InvoiceEmailLogs\InvoiceEmailLogResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceEmailLogs extends ListRecords
{
    use HasCompanyBreadcrumb;

    protected static string $resource = InvoiceEmailLogResource::class;
}
