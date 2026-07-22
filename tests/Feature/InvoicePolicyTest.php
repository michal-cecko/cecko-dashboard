<?php

namespace Tests\Feature;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\User;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Invoices\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithCapabilities(array $capabilities): User
    {
        return User::factory()->create([
            'capabilities' => $capabilities,
        ]);
    }

    public function test_user_with_view_invoices_can_view_any(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_INVOICES]);

        $this->assertTrue($user->can('viewAny', Invoice::class));
    }

    public function test_user_without_view_invoices_cannot_view_any(): void
    {
        $user = $this->createUserWithCapabilities([]);

        $this->assertFalse($user->can('viewAny', Invoice::class));
    }

    public function test_user_with_view_invoices_can_view_invoice(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_INVOICES]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertTrue($user->can('view', $invoice));
    }

    public function test_user_with_manage_invoices_can_create(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_INVOICES]);

        $this->assertTrue($user->can('create', Invoice::class));
    }

    public function test_user_without_manage_invoices_cannot_create(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_INVOICES]);

        $this->assertFalse($user->can('create', Invoice::class));
    }

    public function test_user_with_manage_invoices_can_update(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_INVOICES]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertTrue($user->can('update', $invoice));
    }

    public function test_user_with_manage_invoices_can_delete(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_INVOICES]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertTrue($user->can('delete', $invoice));
    }

    public function test_user_without_manage_invoices_cannot_delete(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_INVOICES]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertFalse($user->can('delete', $invoice));
    }

    public function test_company_owner_can_view_own_company(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_INVOICES]);
        $company = Company::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('view', $company));
    }

    public function test_user_cannot_view_other_company_without_view_all(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::VIEW_INVOICES]);
        $otherUser = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($user->can('view', $company));
    }

    public function test_admin_with_view_all_can_view_any_company(): void
    {
        $user = $this->createUserWithCapabilities([
            UserCapabilityEnum::VIEW_INVOICES,
            UserCapabilityEnum::MANAGE_ALL_INVOICES,
        ]);
        $otherUser = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($user->can('view', $company));
    }

    public function test_admin_with_view_all_can_update_any_company(): void
    {
        $user = $this->createUserWithCapabilities([
            UserCapabilityEnum::VIEW_INVOICES,
            UserCapabilityEnum::MANAGE_ALL_INVOICES,
        ]);
        $otherUser = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($user->can('update', $company));
    }

    public function test_owner_with_manage_can_update_own_company(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_INVOICES]);
        $company = Company::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->can('update', $company));
    }

    public function test_non_owner_with_manage_cannot_update_other_company(): void
    {
        $user = $this->createUserWithCapabilities([UserCapabilityEnum::MANAGE_INVOICES]);
        $otherUser = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($user->can('update', $company));
    }
}
