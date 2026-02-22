<?php

namespace Tests\Unit;

use App\Services\Invoices\InvoiceNumberService;
use PHPUnit\Framework\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    private InvoiceNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceNumberService;
    }

    public function test_format_number_with_year_and_sequence(): void
    {
        $result = $this->service->formatNumber('{YEAR}-{SEQ}', 1, 4);

        $this->assertEquals(now()->format('Y').'-0001', $result);
    }

    public function test_format_number_with_short_year(): void
    {
        $result = $this->service->formatNumber('{YY}{SEQ}', 42, 3);

        $this->assertEquals(now()->format('y').'042', $result);
    }

    public function test_format_number_with_month(): void
    {
        $result = $this->service->formatNumber('{YEAR}/{MONTH}-{SEQ}', 5, 3);

        $expected = now()->format('Y').'/'.now()->format('m').'-005';
        $this->assertEquals($expected, $result);
    }

    public function test_format_number_with_no_padding(): void
    {
        $result = $this->service->formatNumber('F-{SEQ}', 7, 1);

        $this->assertEquals('F-7', $result);
    }

    public function test_format_number_with_large_padding(): void
    {
        $result = $this->service->formatNumber('{SEQ}', 1, 6);

        $this->assertEquals('000001', $result);
    }

    public function test_format_number_preserves_literal_text(): void
    {
        $result = $this->service->formatNumber('INV-{YEAR}-{SEQ}', 10, 4);

        $this->assertEquals('INV-'.now()->format('Y').'-0010', $result);
    }
}
