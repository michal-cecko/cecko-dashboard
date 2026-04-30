<?php

namespace Tests\Feature\Garaz;

use App\Enums\Garaz\BikeCategoryEnum;
use App\Enums\Garaz\FuelTypeEnum;
use App\Enums\Garaz\MotorcycleEngineLayoutEnum;
use App\Enums\Garaz\OdometerSourceEnum;
use App\Enums\Garaz\TransmissionEnum;
use App\Enums\Garaz\VehicleTypeEnum;
use App\Models\Common\User;
use App\Models\Garaz\BicycleSpec;
use App\Models\Garaz\CarSpec;
use App\Models\Garaz\MotorcycleSpec;
use App\Models\Garaz\OdometerReading;
use App\Models\Garaz\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_car_spec_resolver_returns_car_spec_instance(): void
    {
        $vehicle = Vehicle::factory()->car()->create();
        CarSpec::create([
            'vehicle_id' => $vehicle->id,
            'fuel_type' => FuelTypeEnum::PETROL,
            'engine_code' => 'B14XFT',
            'transmission' => TransmissionEnum::MANUAL,
        ]);

        $spec = $vehicle->fresh()->spec();

        $this->assertInstanceOf(CarSpec::class, $spec);
        $this->assertEquals('B14XFT', $spec->engine_code);
        $this->assertEquals(FuelTypeEnum::PETROL, $spec->fuel_type);
    }

    public function test_motorcycle_spec_resolver_returns_motorcycle_spec_instance(): void
    {
        $vehicle = Vehicle::factory()->motorcycle()->create();
        MotorcycleSpec::create([
            'vehicle_id' => $vehicle->id,
            'engine_layout' => MotorcycleEngineLayoutEnum::PARALLEL_TWIN,
            'displacement_ccm' => 689,
        ]);

        $spec = $vehicle->fresh()->spec();

        $this->assertInstanceOf(MotorcycleSpec::class, $spec);
        $this->assertEquals(689, $spec->displacement_ccm);
    }

    public function test_bicycle_spec_resolver_returns_bicycle_spec_instance(): void
    {
        $vehicle = Vehicle::factory()->bicycle()->create();
        BicycleSpec::create([
            'vehicle_id' => $vehicle->id,
            'bike_category' => BikeCategoryEnum::MTB_HARDTAIL,
            'has_dropper_post' => true,
        ]);

        $spec = $vehicle->fresh()->spec();

        $this->assertInstanceOf(BicycleSpec::class, $spec);
        $this->assertEquals(BikeCategoryEnum::MTB_HARDTAIL, $spec->bike_category);
        $this->assertTrue($spec->has_dropper_post);
    }

    public function test_spec_resolver_returns_null_when_no_spec_attached(): void
    {
        $vehicle = Vehicle::factory()->car()->create();

        $this->assertNull($vehicle->spec());
    }

    public function test_owned_by_scope_filters_to_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Vehicle::factory()->count(2)->create(['user_id' => $alice->id]);
        Vehicle::factory()->count(3)->create(['user_id' => $bob->id]);

        $this->assertEquals(2, Vehicle::ownedBy($alice)->count());
        $this->assertEquals(3, Vehicle::ownedBy($bob)->count());
    }

    public function test_active_scope_excludes_archived(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->count(2)->create(['user_id' => $user->id, 'archived_at' => null]);
        Vehicle::factory()->create(['user_id' => $user->id, 'archived_at' => now()]);

        $this->assertEquals(2, Vehicle::active()->count());
        $this->assertEquals(3, Vehicle::count());
    }

    public function test_is_archived_returns_true_when_archived_at_set(): void
    {
        $vehicle = Vehicle::factory()->create(['archived_at' => now()]);

        $this->assertTrue($vehicle->isArchived());
    }

    public function test_is_archived_returns_false_for_active_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['archived_at' => null]);

        $this->assertFalse($vehicle->isArchived());
    }

    public function test_odometer_reading_syncs_vehicle_current_km_on_create(): void
    {
        $vehicle = Vehicle::factory()->create([
            'current_odometer_km' => null,
            'current_odometer_at' => null,
        ]);

        OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 87420,
            'recorded_at' => now()->subDays(2),
            'source' => OdometerSourceEnum::INITIAL,
        ]);

        $vehicle->refresh();

        $this->assertEquals(87420, $vehicle->current_odometer_km);
        $this->assertNotNull($vehicle->current_odometer_at);
    }

    public function test_vehicle_current_km_reflects_latest_reading_by_recorded_at(): void
    {
        $vehicle = Vehicle::factory()->create();

        OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 90000,
            'recorded_at' => now()->subDays(10),
            'source' => OdometerSourceEnum::MANUAL,
        ]);
        OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 95000,
            'recorded_at' => now()->subDays(1),
            'source' => OdometerSourceEnum::MANUAL,
        ]);
        OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 80000,
            'recorded_at' => now()->subDays(30),
            'source' => OdometerSourceEnum::MANUAL,
        ]);

        $vehicle->refresh();

        $this->assertEquals(95000, $vehicle->current_odometer_km);
    }

    public function test_deleting_latest_odometer_reading_resyncs_vehicle_to_previous(): void
    {
        $vehicle = Vehicle::factory()->create();

        OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 90000,
            'recorded_at' => now()->subDays(10),
            'source' => OdometerSourceEnum::MANUAL,
        ]);
        $latest = OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 95000,
            'recorded_at' => now()->subDays(1),
            'source' => OdometerSourceEnum::MANUAL,
        ]);

        $latest->delete();
        $vehicle->refresh();

        $this->assertEquals(90000, $vehicle->current_odometer_km);
    }

    public function test_deleting_only_odometer_reading_clears_vehicle_current_km(): void
    {
        $vehicle = Vehicle::factory()->create();

        $reading = OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 50000,
            'recorded_at' => now(),
            'source' => OdometerSourceEnum::MANUAL,
        ]);

        $reading->delete();
        $vehicle->refresh();

        $this->assertNull($vehicle->current_odometer_km);
        $this->assertNull($vehicle->current_odometer_at);
    }

    public function test_vehicle_type_is_cast_to_enum(): void
    {
        $vehicle = Vehicle::factory()->create(['type' => VehicleTypeEnum::CAR]);

        $this->assertInstanceOf(VehicleTypeEnum::class, $vehicle->fresh()->type);
        $this->assertEquals(VehicleTypeEnum::CAR, $vehicle->fresh()->type);
    }

    public function test_deleting_vehicle_cascades_to_specs_and_readings(): void
    {
        $vehicle = Vehicle::factory()->car()->create();
        CarSpec::create(['vehicle_id' => $vehicle->id, 'fuel_type' => FuelTypeEnum::PETROL]);
        OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 100,
            'recorded_at' => now(),
            'source' => OdometerSourceEnum::INITIAL,
        ]);

        $vehicle->delete();

        $this->assertDatabaseCount('car_specs', 0);
        $this->assertDatabaseCount('odometer_readings', 0);
    }
}
