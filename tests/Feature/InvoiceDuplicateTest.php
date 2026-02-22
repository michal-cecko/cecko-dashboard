<?php

namespace Tests\Feature;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Invoices\InvoiceStatusEnum;
use App\Enums\Invoices\VatTypeEnum;
use App\Models\Common\User;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceItem;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Services\Invoices\InvoiceCalculationService;
use App\Services\Invoices\InvoiceNumberService;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Customer $customer;

    private InvoiceNumberSequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'capabilities' => [
                UserCapabilityEnum::VIEW_INVOICES,
                UserCapabilityEnum::MANAGE_INVOICES,
            ],
        ]);
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'default_currency' => 'EUR',
        ]);
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $this->sequence = InvoiceNumberSequence::factory()->create(['company_id' => $this->company->id]);

        $this->user->update(['active_company_id' => $this->company->id]);
        $this->actingAs($this->user);
    }

    public function test_duplicate_creates_new_invoice_with_new_status(): void
    {
        $original = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number_sequence_id' => $this->sequence->id,
            'invoice_number' => 'OLD-9999',
            'status' => InvoiceStatusEnum::PAID,
            'currency' => 'EUR',
            'total' => 240,
            'sent_at' => now(),
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $original->id,
            'quantity' => 2,
            'unit_price' => 100,
            'vat_type' => VatTypeEnum::STANDARD,
            'vat_rate_value' => 20,
            'sort_order' => 0,
        ]);

        // Simulate the duplicate logic
        $newInvoice = $original->replicate([
            'invoice_number',
            'status',
            'sent_at',
            'cancelled_at',
            'deleted_at',
            'subtotal',
            'vat_total',
            'total',
            'subtotal_base',
            'vat_total_base',
            'total_base',
        ]);

        $newInvoice->status = InvoiceStatusEnum::NEW;
        $newInvoice->issue_date = now();
        $newInvoice->due_date = now()->addDays(14);
        $newInvoice->delivery_date = now();
        $newInvoice->invoice_number = app(InvoiceNumberService::class)->generateNextNumber($this->sequence);

        $pdfService = app(InvoicePdfService::class);
        $newInvoice->seller_snapshot = $pdfService->buildSellerSnapshot($this->company);
        $newInvoice->buyer_snapshot = $pdfService->buildBuyerSnapshot($this->customer);

        $newInvoice->save();

        foreach ($original->items as $item) {
            $newItem = $item->replicate(['invoice_id']);
            $newItem->invoice_id = $newInvoice->id;
            $newItem->save();
        }

        app(InvoiceCalculationService::class)->recalculateInvoice($newInvoice);
        $newInvoice->refresh();

        $this->assertNotEquals($original->id, $newInvoice->id);
        $this->assertEquals(InvoiceStatusEnum::NEW, $newInvoice->status);
        $this->assertNotEquals($original->invoice_number, $newInvoice->invoice_number);
        $this->assertNull($newInvoice->sent_at);
        $this->assertEquals($original->customer_id, $newInvoice->customer_id);
        $this->assertEquals($original->currency, $newInvoice->currency);
        $this->assertCount(1, $newInvoice->items);
        $this->assertEquals('200.00', $newInvoice->subtotal);
        $this->assertEquals('40.00', $newInvoice->vat_total);
        $this->assertEquals('240.00', $newInvoice->total);
    }

    public function test_duplicated_invoice_gets_new_invoice_number(): void
    {
        $original = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number_sequence_id' => $this->sequence->id,
            'invoice_number' => 'OLD-1234',
        ]);

        $number = app(InvoiceNumberService::class)->generateNextNumber($this->sequence);

        $this->assertNotEquals($original->invoice_number, $number);
        $this->assertStringContainsString(now()->format('Y'), $number);
    }

    public function test_preview_number_reflects_yearly_reset(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => now()->year - 1,
            'next_number' => 42,
        ]);

        $preview = app(InvoiceNumberService::class)->previewNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0001', $preview);
    }

    public function test_preview_number_shows_current_next_when_no_reset_needed(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => now()->year,
            'next_number' => 15,
        ]);

        $preview = app(InvoiceNumberService::class)->previewNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0015', $preview);
    }

    public function test_duplicate_preserves_multiple_items(): void
    {
        $original = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number_sequence_id' => $this->sequence->id,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $original->id,
            'quantity' => 1,
            'unit_price' => 100,
            'sort_order' => 0,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $original->id,
            'quantity' => 5,
            'unit_price' => 50,
            'sort_order' => 1,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $original->id,
            'quantity' => 3,
            'unit_price' => 200,
            'sort_order' => 2,
        ]);

        $newInvoice = $original->replicate([
            'invoice_number', 'status', 'sent_at', 'cancelled_at', 'deleted_at',
            'subtotal', 'vat_total', 'total', 'subtotal_base', 'vat_total_base', 'total_base',
        ]);
        $newInvoice->status = InvoiceStatusEnum::NEW;
        $newInvoice->invoice_number = app(InvoiceNumberService::class)->generateNextNumber($this->sequence);
        $newInvoice->save();

        foreach ($original->items as $item) {
            $newItem = $item->replicate(['invoice_id']);
            $newItem->invoice_id = $newInvoice->id;
            $newItem->save();
        }

        $this->assertCount(3, $newInvoice->items()->get());
    }
}
