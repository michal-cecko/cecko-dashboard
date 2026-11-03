<?php

namespace Tests\Feature\Common;

use App\Enums\Common\UserCapabilityEnum;
use App\Filament\Common\Pages\Login;
use App\Models\Common\User;
use App\Models\Invoices\Customer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_who_signs_in_on_a_panel_they_cannot_open_goes_to_their_first_panel(): void
    {
        $staff = User::factory()->create([
            'email' => 'agent@test.sk',
            'password' => 'heslo-123',
            'capabilities' => [UserCapabilityEnum::VIEW_TICKETS],
        ]);
        Filament::setCurrentPanel('songs');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'agent@test.sk', 'password' => 'heslo-123'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getPanel('tikety')->getUrl());

        $this->assertAuthenticatedAs($staff);
    }

    public function test_a_client_who_signs_in_on_the_staff_panel_goes_to_the_client_panel(): void
    {
        User::factory()->create([
            'email' => 'klient@test.sk',
            'password' => 'heslo-123',
            'customer_id' => Customer::factory()->create()->id,
            'capabilities' => [],
        ]);
        Filament::setCurrentPanel('tikety');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'klient@test.sk', 'password' => 'heslo-123'])
            ->call('authenticate')
            ->assertRedirect(Filament::getPanel('podpora')->getUrl());
    }

    public function test_a_wrong_password_or_a_user_with_no_panel_gets_the_error(): void
    {
        User::factory()->create(['email' => 'agent@test.sk', 'password' => 'heslo-123', 'capabilities' => [UserCapabilityEnum::VIEW_TICKETS]]);
        User::factory()->create(['email' => 'nikto@test.sk', 'password' => 'heslo-123', 'capabilities' => []]);
        Filament::setCurrentPanel('songs');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'agent@test.sk', 'password' => 'zle-heslo'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'nikto@test.sk', 'password' => 'heslo-123'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_the_home_page_sends_a_signed_in_user_to_their_first_panel(): void
    {
        $client = User::factory()->create(['customer_id' => Customer::factory()->create()->id, 'capabilities' => []]);

        $this->get('/')->assertRedirect(route('filament.songs.home'));
        $this->actingAs($client)->get('/')->assertRedirect(Filament::getPanel('podpora')->getUrl());
    }
}
