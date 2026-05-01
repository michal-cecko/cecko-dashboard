<?php

namespace App\Filament\Invoices\Resources\RecurringInvoices\Pages;

use App\Filament\Invoices\Concerns\HasCompanyBreadcrumb;
use App\Filament\Invoices\Resources\RecurringInvoices\RecurringInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringInvoice extends CreateRecord
{
    use HasCompanyBreadcrumb;

    protected static string $resource = RecurringInvoiceResource::class;

    protected static ?string $title = 'Nová pravidelná faktúra';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['next_generation_date']) && ! empty($data['start_date'])) {
            $data['next_generation_date'] = $data['start_date'];
        }

        return $data;
    }
}
