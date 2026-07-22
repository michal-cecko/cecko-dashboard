<?php

namespace Tests\Feature;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Invoices\Components\CompanySwitcher;
use App\Models\Common\User;
use App\Models\Invoices\Company;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySwitcherTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('invoices'));

        $this->user = User::factory()->create([
            'capabilities' => [
                UserCapabilityEnum::VIEW_INVOICES,
                UserCapabilityEnum::MANAGE_INVOICES,
            ],
        ]);
        $this->company = Company::factory()->create(['user_id' => $this->user->id]);
        $this->user->update(['active_company_id' => $this->company->id]);
    }

    public function test_user_can_switch_between_own_companies(): void
    {
        $secondCompany = Company::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user);

        Livewire::test(CompanySwitcher::class)
            ->call('switchCompany', $secondCompany->id);

        $this->assertEquals($secondCompany->id, $this->user->fresh()->active_company_id);
    }

    public function test_user_cannot_switch_to_foreign_company(): void
    {
        $otherUser = User::factory()->create();
        $foreignCompany = Company::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user);

        Livewire::test(CompanySwitcher::class)
            ->call('switchCompany', $foreignCompany->id);

        $this->assertEquals($this->company->id, $this->user->fresh()->active_company_id);
    }

    public function test_user_cannot_enable_all_companies_mode_without_capability(): void
    {
        $this->actingAs($this->user);

        Livewire::test(CompanySwitcher::class)
            ->call('switchCompany', CompanySwitcher::ALL_COMPANIES);

        $this->assertFalse(session()->get('invoices.show_all_companies', false));
    }

    public function test_manage_all_invoices_user_can_switch_to_foreign_company(): void
    {
        $this->user->update([
            'capabilities' => [
                UserCapabilityEnum::VIEW_INVOICES,
                UserCapabilityEnum::MANAGE_INVOICES,
                UserCapabilityEnum::MANAGE_ALL_INVOICES,
            ],
        ]);

        $otherUser = User::factory()->create();
        $foreignCompany = Company::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user->fresh());

        Livewire::test(CompanySwitcher::class)
            ->call('switchCompany', $foreignCompany->id);

        $this->assertEquals($foreignCompany->id, $this->user->fresh()->active_company_id);
    }

    public function test_manage_all_invoices_user_can_enable_all_companies_mode(): void
    {
        $this->user->update([
            'capabilities' => [
                UserCapabilityEnum::VIEW_INVOICES,
                UserCapabilityEnum::MANAGE_INVOICES,
                UserCapabilityEnum::MANAGE_ALL_INVOICES,
            ],
        ]);

        $this->actingAs($this->user->fresh());

        Livewire::test(CompanySwitcher::class)
            ->call('switchCompany', CompanySwitcher::ALL_COMPANIES);

        $this->assertTrue(session()->get('invoices.show_all_companies'));
    }

    public function test_switching_to_concrete_company_disables_all_companies_mode(): void
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

        Livewire::test(CompanySwitcher::class)
            ->call('switchCompany', $this->company->id);

        $this->assertFalse(session()->get('invoices.show_all_companies', false));
        $this->assertEquals($this->company->id, $this->user->fresh()->active_company_id);
    }
}
