<?php

namespace App\Filament\Invoices\Resources\Invoices\Pages;

use App\Filament\Invoices\Resources\Invoices\InvoiceResource;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Services\Invoices\InvoiceCalculationService;
use App\Services\Invoices\InvoiceNumberService;
use App\Services\Invoices\InvoicePdfService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Nová faktúra';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $sequence = InvoiceNumberSequence::find($data['invoice_number_sequence_id']);

        if ($sequence) {
            $data['invoice_number'] = app(InvoiceNumberService::class)->generateNextNumber($sequence);
        }

        $company = auth()->user()->activeCompany;

        if ($company) {
            $pdfService = app(InvoicePdfService::class);
            $data['seller_snapshot'] = $pdfService->buildSellerSnapshot($company);

            if (! empty($data['customer_id'])) {
                $customer = \App\Models\Invoices\Customer::find($data['customer_id']);
                if ($customer) {
                    $data['buyer_snapshot'] = $pdfService->buildBuyerSnapshot($customer);
                }
            }

            if (! empty($data['exchange_rate']) && ($data['currency'] ?? '') !== $company->default_currency) {
                $data['exchange_rate_date'] = now()->toDateString();
            }
        }

        unset($data['due_days']);

        return $data;
    }

    protected function afterCreate(): void
    {
        app(InvoiceCalculationService::class)->recalculateInvoice($this->record);
    }
}
