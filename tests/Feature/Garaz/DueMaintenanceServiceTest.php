<?php

namespace Tests\Feature\Garaz;

use App\Enums\Garaz\OdometerSourceEnum;
use App\Enums\Garaz\ServiceCategoryEnum;
use App\Enums\Garaz\ServiceSourceEnum;
use App\Models\Common\User;
use App\Models\Garaz\OdometerReading;
use App\Models\Garaz\ServiceRecord;
use App\Models\Garaz\Vehicle;
use App\Services\Garaz\DueMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecord(Vehicle $vehicle, ServiceCategoryEnum $category, string $performedAt, ?int $mileageKm): ServiceRecord
    {
        return ServiceRecord::create([
            'vehicle_id' => $vehicle->id,
            'performed_at' => $performedAt,
            'mileage_km' => $mileageKm,
            'category' => $category,
            'source' => ServiceSourceEnum::SHOP,
        ]);
    }

    public function test_flags_overdue_soon_and_unknown_items(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create(['current_odometer_km' => 100_000]);

        $this->makeRecord($vehicle, ServiceCategoryEnum::OIL_CHANGE, now()->subMonths(14)->toDateString(), 90_000);
        $this->makeRecord($vehicle, ServiceCategoryEnum::CABIN_FILTER, now()->subMonths(23)->toDateString(), 85_000);
        $this->makeRecord($vehicle, ServiceCategoryEnum::AIR_FILTER, now()->subMonths(2)->toDateString(), 99_000);

        $entries = app(DueMaintenanceService::class)->dueForUser($user->id);

        $this->assertCount(1, $entries);
        $items = $entries->first()['items']->keyBy(fn (array $item) => $item['category']->value);

        $this->assertSame('overdue', $items->get('oil_change')['status']);
        $this->assertSame('soon', $items->get('cabin_filter')['status']);
        $this->assertArrayNotHasKey('air_filter', $items->all());
        $this->assertSame('unknown', $items->get('coolant')['status']);
        $this->assertSame('unknown', $items->get('spark_plugs')['status']);
    }

    public function test_km_axis_triggers_overdue_even_when_time_is_fine(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create(['current_odometer_km' => 120_000]);

        $this->makeRecord($vehicle, ServiceCategoryEnum::OIL_CHANGE, now()->subMonths(6)->toDateString(), 100_000);
        OdometerReading::create([
            'vehicle_id' => $vehicle->id,
            'reading_km' => 120_000,
            'recorded_at' => now(),
            'source' => OdometerSourceEnum::MANUAL,
        ]);

        $entries = app(DueMaintenanceService::class)->dueForUser($user->id);
        $oil = $entries->first()['items']->first(fn (array $item) => $item['category'] === ServiceCategoryEnum::OIL_CHANGE);

        $this->assertSame('overdue', $oil['status']);
    }

    public function test_vehicle_without_any_history_is_not_listed(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->for($user)->create();

        $this->assertCount(0, app(DueMaintenanceService::class)->dueForUser($user->id));
    }
}
