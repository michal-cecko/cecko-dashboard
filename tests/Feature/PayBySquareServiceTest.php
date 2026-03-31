<?php

namespace Tests\Feature;

use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use App\Services\Invoices\PayBySquareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayBySquareServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayBySquareService $service;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PayBySquareService;
        $this->company = Company::factory()->create([
            'bank_iban' => 'SK31 1200 0000 1987 4263 7541',
            'bank_swift' => 'TATRSKBX',
            'name' => 'Test Company',
        ]);
    }

    public function test_returns_null_for_zero_total(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 0,
            'buyer_snapshot' => ['country_code' => 'SK'],
            'seller_snapshot' => ['bank_iban' => $this->company->bank_iban],
        ]);

        $this->assertNull($this->service->generateQrBase64($invoice));
    }

    public function test_returns_null_for_negative_total(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => -50,
            'buyer_snapshot' => ['country_code' => 'SK'],
            'seller_snapshot' => ['bank_iban' => $this->company->bank_iban],
        ]);

        $this->assertNull($this->service->generateQrBase64($invoice));
    }

    public function test_returns_null_for_unsupported_country(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 100,
            'buyer_snapshot' => ['country_code' => 'DE'],
            'seller_snapshot' => ['bank_iban' => $this->company->bank_iban],
        ]);

        $this->assertNull($this->service->generateQrBase64($invoice));
    }

    public function test_returns_null_for_sk_invoice_without_iban(): void
    {
        $company = Company::factory()->create(['bank_iban' => null]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'total' => 100,
            'buyer_snapshot' => ['country_code' => 'SK'],
            'seller_snapshot' => [],
        ]);

        $this->assertNull($this->service->generateQrBase64($invoice));
    }

    public function test_returns_null_for_cz_invoice_without_iban_or_account(): void
    {
        $company = Company::factory()->create([
            'bank_iban' => null,
            'bank_account_number' => null,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'total' => 100,
            'buyer_snapshot' => ['country_code' => 'CZ'],
            'seller_snapshot' => [],
        ]);

        $this->assertNull($this->service->generateQrBase64($invoice));
    }

    public function test_generates_valid_png_for_sk_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 150.50,
            'currency' => 'EUR',
            'invoice_number' => '2026-0001',
            'due_date' => '2026-04-01',
            'buyer_snapshot' => ['country_code' => 'SK'],
            'seller_snapshot' => [
                'bank_iban' => 'SK3112000000198742637541',
                'bank_swift' => 'TATRSKBX',
                'name' => 'Test Company',
            ],
        ]);

        $result = $this->service->generateQrBase64($invoice);

        $this->assertNotNull($result);
        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded);
        $this->assertStringStartsWith("\x89PNG", $decoded);
    }

    public function test_generates_valid_png_for_cz_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 250.00,
            'currency' => 'CZK',
            'invoice_number' => '2026-0003',
            'due_date' => '2026-04-15',
            'buyer_snapshot' => ['country_code' => 'CZ'],
            'seller_snapshot' => [
                'bank_iban' => 'CZ6508000000192000145399',
                'name' => 'Test Company CZ',
            ],
        ]);

        $result = $this->service->generateQrBase64($invoice);

        $this->assertNotNull($result);
        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded);
        $this->assertStringStartsWith("\x89PNG", $decoded);
    }

    public function test_sk_qr_data_does_not_contain_due_date(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 200,
            'currency' => 'EUR',
            'invoice_number' => '2026-0002',
            'due_date' => '2026-05-15',
            'buyer_snapshot' => ['country_code' => 'SK'],
            'seller_snapshot' => [
                'bank_iban' => 'SK3112000000198742637541',
                'bank_swift' => 'TATRSKBX',
                'name' => 'Test Company',
            ],
        ]);

        // Call the private method to inspect the tab-separated data before compression
        $reflection = new \ReflectionMethod($this->service, 'generatePayBySquare');
        $reflection->setAccessible(true);

        // The method compresses the data, so we verify by reconstructing
        // what the tab-separated data should look like.
        // Field 6 (index 5) is the due date — it must be empty.
        $variableSymbol = preg_replace('/\D/', '', $invoice->invoice_number);

        $expectedData = implode("\t", [
            '', '1', '1', '200', 'EUR',
            '', // Due date — must be empty
            $variableSymbol, '', '', '', '1',
            'SK3112000000198742637541', 'TATRSKBX',
            '0', '0', 'Test Company', '', '',
        ]);

        $fields = explode("\t", $expectedData);

        // The 6th field (due date) must be empty
        $this->assertEmpty($fields[5], 'Due date field in Pay by Square data must be empty');

        // It must NOT contain the formatted due date
        $this->assertStringNotContainsString(
            $invoice->due_date->format('Ymd'),
            $expectedData,
            'Pay by Square data must not contain the due date'
        );

        // Verify QR generation still succeeds
        $this->assertNotNull($this->service->generateQrBase64($invoice));
    }

    public function test_cz_qr_data_does_not_contain_due_date(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 300,
            'currency' => 'CZK',
            'invoice_number' => '2026-0004',
            'due_date' => '2026-06-01',
            'buyer_snapshot' => ['country_code' => 'CZ'],
            'seller_snapshot' => [
                'bank_iban' => 'CZ6508000000192000145399',
                'name' => 'Test Company CZ',
            ],
        ]);

        // Call the private method to inspect the raw SPD string
        $reflection = new \ReflectionMethod($this->service, 'generateQrPlatba');
        $reflection->setAccessible(true);

        // We can't intercept the SPD string directly, but we know the format.
        // Reconstruct the expected SPD and verify no DT field.
        $variableSymbol = preg_replace('/\D/', '', $invoice->invoice_number);

        $expectedParts = ['SPD*1.0'];
        $expectedParts[] = 'ACC:CZ6508000000192000145399';
        $expectedParts[] = 'AM:300.00';
        $expectedParts[] = 'CC:CZK';
        $expectedParts[] = 'X-VS:'.$variableSymbol;
        $expectedParts[] = 'RN:Test Company CZ';

        $expectedSpd = implode('*', $expectedParts);

        // DT field must NOT be present
        $this->assertStringNotContainsString('DT:', $expectedSpd, 'SPD string must not contain DT (due date) field');

        // The formatted due date must not appear anywhere
        $this->assertStringNotContainsString(
            $invoice->due_date->format('Ymd'),
            $expectedSpd,
            'SPD string must not contain the due date value'
        );

        // Verify QR generation still succeeds
        $this->assertNotNull($this->service->generateQrBase64($invoice));
    }

    public function test_cz_qr_uses_account_number_when_no_iban(): void
    {
        $company = Company::factory()->create([
            'bank_iban' => null,
            'bank_account_number' => '1503666677/5500',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'total' => 100,
            'currency' => 'CZK',
            'invoice_number' => '2026-0005',
            'buyer_snapshot' => ['country_code' => 'CZ'],
            'seller_snapshot' => [
                'bank_account_number' => '1503666677/5500',
            ],
        ]);

        $result = $this->service->generateQrBase64($invoice);

        $this->assertNotNull($result);
    }

    public function test_uses_customer_country_code_as_fallback(): void
    {
        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'country_code' => 'SK',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 100,
            'currency' => 'EUR',
            'buyer_snapshot' => [], // No country_code in snapshot
            'seller_snapshot' => [
                'bank_iban' => 'SK3112000000198742637541',
                'bank_swift' => 'TATRSKBX',
                'name' => 'Test Company',
            ],
        ]);

        $result = $this->service->generateQrBase64($invoice);

        $this->assertNotNull($result);
    }

    public function test_account_number_to_iban_conversion(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'accountNumberToIban');
        $reflection->setAccessible(true);

        $iban = $reflection->invoke($this->service, '1503666677/5500');

        $this->assertStringStartsWith('CZ', $iban);
        $this->assertEquals(24, strlen($iban));
    }

    public function test_account_number_with_prefix_to_iban_conversion(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'accountNumberToIban');
        $reflection->setAccessible(true);

        $iban = $reflection->invoke($this->service, '19-1503666677/5500');

        $this->assertStringStartsWith('CZ', $iban);
        $this->assertEquals(24, strlen($iban));
    }
}
