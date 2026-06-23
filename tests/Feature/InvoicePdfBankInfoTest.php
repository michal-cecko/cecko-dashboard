<?php

namespace Tests\Feature;

use App\Enums\Invoices\PaymentMethodEnum;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfBankInfoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'default_currency' => 'EUR',
            'default_locale' => 'sk',
        ]);
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    }

    private function makeInvoice(PaymentMethodEnum $paymentMethod): Invoice
    {
        return Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'payment_method' => $paymentMethod,
            'invoice_number' => '2026-0001',
            'total' => 100,
        ]);
    }

    public function test_bank_info_and_qr_are_shown_for_bank_transfer(): void
    {
        $invoice = $this->makeInvoice(PaymentMethodEnum::BANK_TRANSFER);

        $html = app(InvoicePdfService::class)->generateHtml($invoice);

        $this->assertStringContainsString($this->company->bank_iban, $html);
        $this->assertStringContainsString('alt="QR"', $html);
    }

    public function test_bank_info_and_qr_are_hidden_for_cash(): void
    {
        $invoice = $this->makeInvoice(PaymentMethodEnum::CASH);

        $html = app(InvoicePdfService::class)->generateHtml($invoice);

        $this->assertStringNotContainsString($this->company->bank_iban, $html);
        $this->assertStringNotContainsString('alt="QR"', $html);
    }

    public function test_bank_info_and_qr_are_hidden_for_card(): void
    {
        $invoice = $this->makeInvoice(PaymentMethodEnum::CARD);

        $html = app(InvoicePdfService::class)->generateHtml($invoice);

        $this->assertStringNotContainsString($this->company->bank_iban, $html);
        $this->assertStringNotContainsString('alt="QR"', $html);
    }
}
