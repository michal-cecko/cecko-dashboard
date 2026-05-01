<?php

namespace App\Filament\Invoices\Resources\RecurringInvoices\Pages;

use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Resources\RecurringInvoices\RecurringInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringInvoices extends ListRecords
{
    use HasCompanyBreadcrumb;

    protected static string $resource = RecurringInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nová pravidelná faktúra'),
        ];
    }
}
