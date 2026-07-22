<?php

namespace Tests\Feature;

use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfVatPayerNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Company $company): Invoice
    {
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        return Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => '2026-0001',
            'total' => 100,
        ]);
    }

    public function test_notice_is_shown_when_company_is_not_a_vat_payer(): void
    {
        $company = Company::factory()->create([
            'is_vat_payer' => false,
            'default_locale' => 'cs',
        ]);
        $invoice = $this->makeInvoice($company);

        $html = app(InvoicePdfService::class)->generateHtml($invoice);

        $this->assertStringContainsString('Nejsme plátci DPH.', $html);
    }

    public function test_notice_is_hidden_when_company_is_a_vat_payer(): void
    {
        $company = Company::factory()->vatPayer()->create([
            'default_locale' => 'cs',
        ]);
        $invoice = $this->makeInvoice($company);

        $html = app(InvoicePdfService::class)->generateHtml($invoice);

        $this->assertStringNotContainsString('Nejsme plátci DPH.', $html);
    }

    public function test_notice_is_hidden_when_stored_seller_snapshot_lacks_the_flag(): void
    {
        $company = Company::factory()->create([
            'is_vat_payer' => false,
            'default_locale' => 'cs',
        ]);
        $invoice = $this->makeInvoice($company);
        $snapshot = app(InvoicePdfService::class)->buildSellerSnapshot($company);
        unset($snapshot['is_vat_payer']);
        $invoice->update(['seller_snapshot' => $snapshot]);

        $html = app(InvoicePdfService::class)->generateHtml($invoice->fresh());

        $this->assertStringNotContainsString('Nejsme plátci DPH.', $html);
    }
}
