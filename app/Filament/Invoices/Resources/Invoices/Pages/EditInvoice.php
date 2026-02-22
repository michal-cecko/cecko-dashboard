<?php

namespace App\Filament\Invoices\Resources\Invoices\Pages;

use App\Filament\Invoices\Resources\Invoices\InvoiceResource;
use App\Services\Invoices\InvoiceCalculationService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Faktúra '.$this->getRecord()->invoice_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $company = auth()->user()->activeCompany;

        if ($company && ! empty($data['exchange_rate']) && ($data['currency'] ?? '') !== $company->default_currency) {
            $data['exchange_rate_date'] = now()->toDateString();
        }

        unset($data['due_days']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(InvoiceCalculationService::class)->recalculateInvoice($this->record);
    }
}
