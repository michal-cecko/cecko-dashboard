<?php

namespace Tests\Feature\Garaz;

use App\Enums\Common\UserCapabilityEnum;
use App\Enums\Garaz\VehicleTypeEnum;
use App\Filament\Garaz\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Garaz\Resources\Vehicles\Pages\ListVehicles;
use App\Models\Common\User;
use App\Models\Garaz\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'capabilities' => [
                UserCapabilityEnum::VIEW_GARAZ,
                UserCapabilityEnum::MANAGE_GARAZ,
            ],
        ]);

        $this->actingAs($this->user);
        Filament::setCurrentPanel('garaz');
    }

    public function test_user_sees_only_their_own_vehicles_in_list(): void
    {
        $other = User::factory()->create();

        $own = Vehicle::factory()->count(2)->create(['user_id' => $this->user->id]);
        Vehicle::factory()->count(3)->create(['user_id' => $other->id]);

        Livewire::test(ListVehicles::class)
            ->assertCanSeeTableRecords($own)
            ->assertCountTableRecords(2);
    }

    public function test_user_can_create_a_vehicle(): void
    {
        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'type' => VehicleTypeEnum::CAR->value,
                'nickname' => 'Astra K',
                'make' => 'Opel',
                'model' => 'Astra K Sports Tourer',
                'year_of_manufacture' => 2016,
                'license_plate' => 'BL-001 AB',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $this->user->id,
            'nickname' => 'Astra K',
            'make' => 'Opel',
            'type' => VehicleTypeEnum::CAR->value,
        ]);
    }

    public function test_create_form_requires_nickname_and_type(): void
    {
        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'type' => null,
                'nickname' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'type' => 'required',
                'nickname' => 'required',
            ]);
    }

    public function test_user_cannot_view_another_users_vehicle(): void
    {
        $other = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $other->id]);

        $this->assertFalse($this->user->can('view', $vehicle));
        $this->assertFalse($this->user->can('update', $vehicle));
        $this->assertFalse($this->user->can('delete', $vehicle));
    }

    public function test_user_can_manage_their_own_vehicle_via_policy(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->user->id]);

        $this->assertTrue($this->user->can('view', $vehicle));
        $this->assertTrue($this->user->can('update', $vehicle));
        $this->assertTrue($this->user->can('delete', $vehicle));
    }

    public function test_user_without_view_garaz_capability_cannot_access_panel(): void
    {
        $stripped = User::factory()->create(['capabilities' => []]);

        $panel = Filament::getPanel('garaz');

        $this->assertFalse($stripped->canAccessPanel($panel));
        $this->assertTrue($this->user->canAccessPanel($panel));
    }

    public function test_archived_vehicles_hidden_by_default_via_filter(): void
    {
        $active = Vehicle::factory()->count(2)->create(['user_id' => $this->user->id, 'archived_at' => null]);
        $archived = Vehicle::factory()->create(['user_id' => $this->user->id, 'archived_at' => now()]);

        Livewire::test(ListVehicles::class)
            ->assertCanSeeTableRecords($active)
            ->assertCanNotSeeTableRecords([$archived]);
    }
}
