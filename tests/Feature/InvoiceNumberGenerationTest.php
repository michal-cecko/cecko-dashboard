<?php

namespace Tests\Feature;

use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Services\Invoices\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceNumberService $service;

    private Company $company;

    private Customer $customer;

    private InvoiceNumberSequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InvoiceNumberService::class);

        $this->company = Company::factory()->create();
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $this->sequence = InvoiceNumberSequence::factory()->create([
            'company_id' => $this->company->id,
            'format' => '{YEAR}-{SEQ}',
            'next_number' => 1,
            'padding' => 4,
            'reset_yearly' => false,
            'last_reset_year' => null,
        ]);
    }

    private function createInvoice(string $invoiceNumber, ?InvoiceNumberSequence $sequence = null): Invoice
    {
        return Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number_sequence_id' => ($sequence ?? $this->sequence)->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => now(),
        ]);
    }

    public function test_generate_next_number_returns_formatted_number(): void
    {
        $number = $this->service->generateNextNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0001', $number);
    }

    public function test_generate_does_not_consume_number_until_invoice_exists(): void
    {
        $first = $this->service->generateNextNumber($this->sequence);
        $second = $this->service->generateNextNumber($this->sequence);

        $this->assertEquals($first, $second);
        $this->assertDatabaseHas('invoice_number_sequences', [
            'id' => $this->sequence->id,
            'next_number' => 1,
        ]);
    }

    public function test_consecutive_generate_calls_produce_sequential_numbers(): void
    {
        $first = $this->service->generateNextNumber($this->sequence);
        $this->createInvoice($first);

        $second = $this->service->generateNextNumber($this->sequence);
        $this->createInvoice($second);

        $third = $this->service->generateNextNumber($this->sequence);

        $year = now()->format('Y');
        $this->assertEquals("{$year}-0001", $first);
        $this->assertEquals("{$year}-0002", $second);
        $this->assertEquals("{$year}-0003", $third);
    }

    public function test_preview_matches_what_generate_will_produce(): void
    {
        $preview = $this->service->previewNumber($this->sequence);
        $generated = $this->service->generateNextNumber($this->sequence);

        $this->assertEquals($preview, $generated);
    }

    public function test_preview_after_invoice_created_shows_next_number(): void
    {
        $this->createInvoice($this->service->generateNextNumber($this->sequence));

        $preview = $this->service->previewNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0002', $preview);
    }

    public function test_deleting_latest_invoice_releases_its_number(): void
    {
        $year = now()->format('Y');
        $this->createInvoice("{$year}-0001");
        $this->createInvoice("{$year}-0002");
        $latest = $this->createInvoice("{$year}-0003");

        $latest->delete();

        $this->assertEquals("{$year}-0003", $this->service->generateNextNumber($this->sequence));
    }

    public function test_deleting_all_invoices_resets_to_first_number(): void
    {
        $year = now()->format('Y');
        $this->createInvoice("{$year}-0001")->delete();
        $this->createInvoice("{$year}-0002")->delete();

        $this->assertEquals("{$year}-0001", $this->service->generateNextNumber($this->sequence));
    }

    public function test_deleting_middle_invoice_keeps_highest_plus_one(): void
    {
        $year = now()->format('Y');
        $this->createInvoice("{$year}-0001");
        $middle = $this->createInvoice("{$year}-0002");
        $this->createInvoice("{$year}-0003");

        $middle->delete();

        $this->assertEquals("{$year}-0004", $this->service->generateNextNumber($this->sequence));
    }

    public function test_preview_excluding_invoice_ignores_its_own_number(): void
    {
        $year = now()->format('Y');
        $this->createInvoice("{$year}-0001");
        $latest = $this->createInvoice("{$year}-0002");

        $preview = $this->service->previewNumber($this->sequence, $latest->getKey());

        $this->assertEquals("{$year}-0002", $preview);
    }

    public function test_preview_excluding_only_invoice_falls_back_to_first_number(): void
    {
        $only = $this->createInvoice(now()->format('Y').'-0001');

        $preview = $this->service->previewNumber($this->sequence, $only->getKey());

        $this->assertEquals(now()->format('Y').'-0001', $preview);
    }

    public function test_manually_entered_number_matching_format_advances_sequence(): void
    {
        $year = now()->format('Y');
        $this->createInvoice("{$year}-0010");

        $this->assertEquals("{$year}-0011", $this->service->generateNextNumber($this->sequence));
    }

    public function test_numbers_not_matching_format_are_ignored(): void
    {
        $this->createInvoice('CUSTOM-0077');

        $this->assertEquals(now()->format('Y').'-0001', $this->service->generateNextNumber($this->sequence));
    }

    public function test_numbers_from_other_sequences_are_ignored(): void
    {
        $otherSequence = InvoiceNumberSequence::factory()->create([
            'company_id' => $this->company->id,
            'format' => '{YEAR}-{SEQ}',
            'reset_yearly' => false,
            'is_default' => false,
        ]);
        $this->createInvoice(now()->format('Y').'-0050', $otherSequence);

        $this->assertEquals(now()->format('Y').'-0001', $this->service->generateNextNumber($this->sequence));
    }

    public function test_generate_with_custom_starting_number_when_no_invoices_exist(): void
    {
        $this->sequence->update(['next_number' => 100]);
        $this->sequence->refresh();

        $this->assertEquals(now()->format('Y').'-0100', $this->service->generateNextNumber($this->sequence));
    }

    public function test_generate_with_yearly_reset_ignores_previous_year_invoices(): void
    {
        $this->sequence->update(['reset_yearly' => true]);
        $this->sequence->refresh();

        $lastYear = now()->year - 1;
        $this->createInvoice("{$lastYear}-0042");

        $this->assertEquals(now()->format('Y').'-0001', $this->service->generateNextNumber($this->sequence));
    }

    public function test_generate_with_yearly_reset_continues_current_year(): void
    {
        $this->sequence->update(['reset_yearly' => true]);
        $this->sequence->refresh();

        $year = now()->format('Y');
        $this->createInvoice("{$year}-0014");

        $this->assertEquals("{$year}-0015", $this->service->generateNextNumber($this->sequence));
    }

    public function test_consecutive_generates_after_yearly_reset_are_sequential(): void
    {
        $this->sequence->update(['reset_yearly' => true, 'next_number' => 42]);
        $this->sequence->refresh();

        $lastYear = now()->year - 1;
        $this->createInvoice("{$lastYear}-0042");

        $first = $this->service->generateNextNumber($this->sequence);
        $this->createInvoice($first);

        $second = $this->service->generateNextNumber($this->sequence);
        $this->createInvoice($second);

        $third = $this->service->generateNextNumber($this->sequence);

        $year = now()->format('Y');
        $this->assertEquals("{$year}-0001", $first);
        $this->assertEquals("{$year}-0002", $second);
        $this->assertEquals("{$year}-0003", $third);
    }

    public function test_generate_with_yearly_reset_ignores_stale_next_number(): void
    {
        $this->sequence->update(['reset_yearly' => true, 'next_number' => 42, 'last_reset_year' => null]);
        $this->sequence->refresh();

        $this->assertEquals(now()->format('Y').'-0001', $this->service->generateNextNumber($this->sequence));
    }

    public function test_yearly_reset_without_year_in_format_uses_issue_date_year(): void
    {
        $this->sequence->update(['reset_yearly' => true, 'format' => 'F-{SEQ}']);
        $this->sequence->refresh();

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number_sequence_id' => $this->sequence->id,
            'invoice_number' => 'F-0042',
            'issue_date' => now()->subYear(),
        ]);

        $this->assertEquals('F-0001', $this->service->generateNextNumber($this->sequence));
    }
}
