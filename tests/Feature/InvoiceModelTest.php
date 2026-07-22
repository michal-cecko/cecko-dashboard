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
use App\Models\Invoices\InvoicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Customer $customer;

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

        $this->user->update(['active_company_id' => $this->company->id]);
    }

    public function test_is_editable_returns_true_for_new_invoice(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertTrue($invoice->isEditable());
    }

    public function test_is_editable_returns_false_for_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatusEnum::SENT,
        ]);

        $this->assertFalse($invoice->isEditable());
    }

    public function test_is_editable_returns_false_for_paid_invoice(): void
    {
        $invoice = Invoice::factory()->paid()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertFalse($invoice->isEditable());
    }

    public function test_paid_amount_sums_payments(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 300,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 100,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 50,
        ]);

        $this->assertEquals(150.0, $invoice->paidAmount());
    }

    public function test_remaining_amount(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 200,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 75,
        ]);

        $this->assertEquals(125.0, $invoice->remainingAmount());
    }

    public function test_remaining_amount_never_negative(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 100,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 150,
        ]);

        $this->assertEquals(0.0, $invoice->remainingAmount());
    }

    public function test_is_paid_when_fully_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 100,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 100,
        ]);

        $this->assertTrue($invoice->isPaid());
    }

    public function test_is_paid_when_overpaid(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 100,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 120,
        ]);

        $this->assertTrue($invoice->isPaid());
    }

    public function test_is_not_paid_when_partially_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 100,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 50,
        ]);

        $this->assertFalse($invoice->isPaid());
    }

    public function test_payment_percentage(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 200,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 100,
        ]);

        $this->assertEquals(50, $invoice->paymentPercentage());
    }

    public function test_payment_percentage_caps_at_hundred(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 100,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now(),
            'amount' => 200,
        ]);

        $this->assertEquals(100, $invoice->paymentPercentage());
    }

    public function test_payment_percentage_zero_for_zero_total(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total' => 0,
        ]);

        $this->assertEquals(0, $invoice->paymentPercentage());
    }

    public function test_invoice_item_auto_calculates_on_save(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'quantity' => 3,
            'unit' => 'ks',
            'unit_price' => 100,
            'vat_type' => VatTypeEnum::STANDARD,
            'vat_rate_value' => 20,
            'sort_order' => 0,
        ]);

        $this->assertEquals('300.00', $item->subtotal);
        $this->assertEquals('60.00', $item->vat_amount);
        $this->assertEquals('360.00', $item->total);
    }

    public function test_invoice_item_reverse_charge_has_zero_vat(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'quantity' => 2,
            'unit' => 'ks',
            'unit_price' => 500,
            'vat_type' => VatTypeEnum::REVERSE_CHARGE,
            'vat_rate_value' => 20,
            'sort_order' => 0,
        ]);

        $this->assertEquals('1000.00', $item->subtotal);
        $this->assertEquals('0.00', $item->vat_amount);
        $this->assertEquals('1000.00', $item->total);
    }

    public function test_belongs_to_active_company_scopes_queries(): void
    {
        $this->actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create(['user_id' => $otherUser->id]);
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        Invoice::factory()->draft()->create([
            'company_id' => $otherCompany->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $invoices = Invoice::all();

        $this->assertCount(1, $invoices);
        $this->assertEquals($this->company->id, $invoices->first()->company_id);
    }

    public function test_user_without_active_company_sees_no_invoices(): void
    {
        Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $userWithoutCompany = User::factory()->create([
            'capabilities' => [UserCapabilityEnum::VIEW_INVOICES],
        ]);

        $this->actingAs($userWithoutCompany);

        $this->assertCount(0, Invoice::all());
    }

    public function test_manage_all_invoices_user_is_scoped_by_default(): void
    {
        $this->user->update([
            'capabilities' => [
                UserCapabilityEnum::VIEW_INVOICES,
                UserCapabilityEnum::MANAGE_INVOICES,
                UserCapabilityEnum::MANAGE_ALL_INVOICES,
            ],
        ]);

        $this->actingAs($this->user->fresh());

        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create(['user_id' => $otherUser->id]);

        Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        Invoice::factory()->draft()->create([
            'company_id' => $otherCompany->id,
            'customer_id' => $this->customer->id,
        ]);

        $invoices = Invoice::all();

        $this->assertCount(1, $invoices);
        $this->assertEquals($this->company->id, $invoices->first()->company_id);
    }

    public function test_manage_all_invoices_user_sees_all_invoices_with_session_flag(): void
    {
        $this->user->update([
            'capabilities' => [
                UserCapabilityEnum::VIEW_INVOICES,
                UserCapabilityEnum::MANAGE_INVOICES,
                UserCapabilityEnum::MANAGE_ALL_INVOICES,
            ],
        ]);

        $this->actingAs($this->user->fresh());
        session()->put('invoices.show_all_companies', true);

        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create(['user_id' => $otherUser->id]);

        Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        Invoice::factory()->draft()->create([
            'company_id' => $otherCompany->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertCount(2, Invoice::all());
    }

    public function test_session_flag_does_not_unscope_user_without_capability(): void
    {
        $this->actingAs($this->user);
        session()->put('invoices.show_all_companies', true);

        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create(['user_id' => $otherUser->id]);

        Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        Invoice::factory()->draft()->create([
            'company_id' => $otherCompany->id,
            'customer_id' => $this->customer->id,
        ]);

        $invoices = Invoice::all();

        $this->assertCount(1, $invoices);
        $this->assertEquals($this->company->id, $invoices->first()->company_id);
    }

    public function test_customers_are_shared_across_companies(): void
    {
        $this->actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create(['user_id' => $otherUser->id]);
        Customer::factory()->create(['company_id' => $otherCompany->id]);

        $this->assertCount(2, Customer::all());
    }

    public function test_soft_deleted_invoice_can_be_restored(): void
    {
        $invoice = Invoice::factory()->draft()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $invoice->delete();

        $this->actingAs($this->user);
        $this->assertCount(0, Invoice::all());

        $invoice->restore();
        $this->assertCount(1, Invoice::all());
    }
}
