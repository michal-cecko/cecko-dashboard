<?php

namespace Tests\Feature;

use App\Models\Invoices\Company;
use App\Models\Invoices\InvoiceNumberSequence;
use App\Services\Invoices\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceNumberService $service;

    private InvoiceNumberSequence $sequence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InvoiceNumberService::class);

        $company = Company::factory()->create();
        $this->sequence = InvoiceNumberSequence::factory()->create([
            'company_id' => $company->id,
            'format' => '{YEAR}-{SEQ}',
            'next_number' => 1,
            'padding' => 4,
            'reset_yearly' => false,
            'last_reset_year' => null,
        ]);
    }

    public function test_generate_next_number_returns_formatted_number(): void
    {
        $number = $this->service->generateNextNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0001', $number);
    }

    public function test_generate_next_number_increments_next_number_in_db(): void
    {
        $this->service->generateNextNumber($this->sequence);

        $this->assertDatabaseHas('invoice_number_sequences', [
            'id' => $this->sequence->id,
            'next_number' => 2,
        ]);
    }

    public function test_consecutive_generate_calls_produce_sequential_numbers(): void
    {
        $first = $this->service->generateNextNumber($this->sequence);
        $second = $this->service->generateNextNumber($this->sequence);
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

    public function test_preview_after_generate_shows_next_number(): void
    {
        $this->service->generateNextNumber($this->sequence);
        $this->sequence->refresh();

        $preview = $this->service->previewNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0002', $preview);
    }

    public function test_generate_with_yearly_reset_returns_0001_when_year_changed(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => now()->year - 1,
            'next_number' => 42,
        ]);
        $this->sequence->refresh();

        $number = $this->service->generateNextNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0001', $number);
    }

    public function test_generate_with_yearly_reset_persists_new_last_reset_year_to_db(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => now()->year - 1,
            'next_number' => 42,
        ]);
        $this->sequence->refresh();

        $this->service->generateNextNumber($this->sequence);

        $this->assertDatabaseHas('invoice_number_sequences', [
            'id' => $this->sequence->id,
            'last_reset_year' => now()->year,
        ]);
    }

    public function test_generate_with_yearly_reset_persists_incremented_next_number_to_db(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => now()->year - 1,
            'next_number' => 42,
        ]);
        $this->sequence->refresh();

        $this->service->generateNextNumber($this->sequence);

        $this->assertDatabaseHas('invoice_number_sequences', [
            'id' => $this->sequence->id,
            'next_number' => 2,
        ]);
    }

    public function test_consecutive_generates_after_yearly_reset_are_sequential(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => now()->year - 1,
            'next_number' => 42,
        ]);
        $this->sequence->refresh();

        $first = $this->service->generateNextNumber($this->sequence);
        $second = $this->service->generateNextNumber($this->sequence);
        $third = $this->service->generateNextNumber($this->sequence);

        $year = now()->format('Y');
        $this->assertEquals("{$year}-0001", $first);
        $this->assertEquals("{$year}-0002", $second);
        $this->assertEquals("{$year}-0003", $third);
    }

    public function test_generate_does_not_reset_when_last_reset_year_is_current(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => now()->year,
            'next_number' => 15,
        ]);
        $this->sequence->refresh();

        $number = $this->service->generateNextNumber($this->sequence);

        $this->assertEquals(now()->format('Y').'-0015', $number);
        $this->assertDatabaseHas('invoice_number_sequences', [
            'id' => $this->sequence->id,
            'next_number' => 16,
        ]);
    }

    public function test_generate_with_null_last_reset_year_resets_and_stays_sequential(): void
    {
        $this->sequence->update([
            'reset_yearly' => true,
            'last_reset_year' => null,
            'next_number' => 5,
        ]);
        $this->sequence->refresh();

        $first = $this->service->generateNextNumber($this->sequence);
        $second = $this->service->generateNextNumber($this->sequence);

        $year = now()->format('Y');
        $this->assertEquals("{$year}-0001", $first);
        $this->assertEquals("{$year}-0002", $second);
    }
}
