<?php

namespace Tests\Feature;

use App\Enums\Invoices\VatTypeEnum;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceItem;
use App\Services\Invoices\InvoiceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceCalculationService;
    }

    public function test_calculate_item_totals_with_standard_vat(): void
    {
        $items = [
            [
                'quantity' => '2',
                'unit_price' => '100.00',
                'vat_rate_value' => '20',
                'vat_type' => VatTypeEnum::STANDARD,
            ],
        ];

        $result = $this->service->calculateItemTotals($items);

        $this->assertEquals('200.00', $result['subtotal']);
        $this->assertEquals('40.00', $result['vat_total']);
        $this->assertEquals('240.00', $result['total']);
    }

    public function test_calculate_item_totals_with_zero_rate_vat(): void
    {
        $items = [
            [
                'quantity' => '5',
                'unit_price' => '50.00',
                'vat_rate_value' => '20',
                'vat_type' => VatTypeEnum::ZERO_RATE,
            ],
        ];

        $result = $this->service->calculateItemTotals($items);

        $this->assertEquals('250.00', $result['subtotal']);
        $this->assertEquals('0.00', $result['vat_total']);
        $this->assertEquals('250.00', $result['total']);
    }

    public function test_calculate_item_totals_with_reverse_charge(): void
    {
        $items = [
            [
                'quantity' => '1',
                'unit_price' => '1000.00',
                'vat_rate_value' => '20',
                'vat_type' => VatTypeEnum::REVERSE_CHARGE,
            ],
        ];

        $result = $this->service->calculateItemTotals($items);

        $this->assertEquals('1000.00', $result['subtotal']);
        $this->assertEquals('0.00', $result['vat_total']);
        $this->assertEquals('1000.00', $result['total']);
    }

    public function test_calculate_item_totals_with_multiple_items(): void
    {
        $items = [
            [
                'quantity' => '2',
                'unit_price' => '100.00',
                'vat_rate_value' => '20',
                'vat_type' => VatTypeEnum::STANDARD,
            ],
            [
                'quantity' => '3',
                'unit_price' => '50.00',
                'vat_rate_value' => '10',
                'vat_type' => VatTypeEnum::STANDARD,
            ],
        ];

        $result = $this->service->calculateItemTotals($items);

        $this->assertEquals('350.00', $result['subtotal']);
        $this->assertEquals('55.00', $result['vat_total']);
        $this->assertEquals('405.00', $result['total']);
    }

    public function test_calculate_item_totals_with_mixed_vat_types(): void
    {
        $items = [
            [
                'quantity' => '1',
                'unit_price' => '100.00',
                'vat_rate_value' => '20',
                'vat_type' => VatTypeEnum::STANDARD,
            ],
            [
                'quantity' => '1',
                'unit_price' => '200.00',
                'vat_rate_value' => '20',
                'vat_type' => VatTypeEnum::REVERSE_CHARGE,
            ],
        ];

        $result = $this->service->calculateItemTotals($items);

        $this->assertEquals('300.00', $result['subtotal']);
        $this->assertEquals('20.00', $result['vat_total']);
        $this->assertEquals('320.00', $result['total']);
    }

    public function test_calculate_item_totals_with_empty_items(): void
    {
        $result = $this->service->calculateItemTotals([]);

        $this->assertEquals('0.00', $result['subtotal']);
        $this->assertEquals('0.00', $result['vat_total']);
        $this->assertEquals('0.00', $result['total']);
    }

    public function test_calculate_item_totals_with_string_vat_type(): void
    {
        $items = [
            [
                'quantity' => '1',
                'unit_price' => '100.00',
                'vat_rate_value' => '20',
                'vat_type' => 'standard',
            ],
        ];

        $result = $this->service->calculateItemTotals($items);

        $this->assertEquals('100.00', $result['subtotal']);
        $this->assertEquals('20.00', $result['vat_total']);
        $this->assertEquals('120.00', $result['total']);
    }

    public function test_recalculate_invoice_totals(): void
    {
        $company = Company::factory()->create(['default_currency' => 'EUR']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 2,
            'unit_price' => 100,
            'vat_type' => VatTypeEnum::STANDARD,
            'vat_rate_value' => 20,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 50,
            'vat_type' => VatTypeEnum::STANDARD,
            'vat_rate_value' => 20,
        ]);

        $this->service->recalculateInvoice($invoice);

        $invoice->refresh();

        $this->assertEquals('250.00', $invoice->subtotal);
        $this->assertEquals('50.00', $invoice->vat_total);
        $this->assertEquals('300.00', $invoice->total);
    }

    public function test_recalculate_invoice_with_exchange_rate(): void
    {
        $company = Company::factory()->create(['default_currency' => 'EUR']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency' => 'CZK',
            'exchange_rate' => 0.04,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'vat_type' => VatTypeEnum::STANDARD,
            'vat_rate_value' => 20,
        ]);

        $this->service->recalculateInvoice($invoice);

        $invoice->refresh();

        $this->assertEquals('1000.00', $invoice->subtotal);
        $this->assertEquals('200.00', $invoice->vat_total);
        $this->assertEquals('1200.00', $invoice->total);
        $this->assertEquals('40.00', $invoice->subtotal_base);
        $this->assertEquals('8.00', $invoice->vat_total_base);
        $this->assertEquals('48.00', $invoice->total_base);
    }

    public function test_recalculate_invoice_same_currency_no_base_conversion(): void
    {
        $company = Company::factory()->create(['default_currency' => 'EUR']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'exchange_rate' => null,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 500,
            'vat_type' => VatTypeEnum::STANDARD,
            'vat_rate_value' => 20,
        ]);

        $this->service->recalculateInvoice($invoice);

        $invoice->refresh();

        $this->assertEquals('500.00', $invoice->subtotal);
        $this->assertNull($invoice->subtotal_base);
    }
}
