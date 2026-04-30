<?php

namespace Tests\Feature\Garaz;

use App\Enums\Garaz\AssessmentVerdictEnum;
use App\Enums\Garaz\CheckOutcomeEnum;
use App\Enums\Garaz\ConcernCheckInputEnum;
use App\Enums\Garaz\ConcernTriggerEnum;
use App\Enums\Garaz\FuelTypeEnum;
use App\Enums\Garaz\VehicleTypeEnum;
use App\Filament\Garaz\Resources\ConcernAssessments\ConcernAssessmentResource;
use App\Models\Common\User;
use App\Models\Garaz\CarSpec;
use App\Models\Garaz\ConcernAssessment;
use App\Models\Garaz\ConcernCheck;
use App\Models\Garaz\MaintenanceConcern;
use App\Models\Garaz\Vehicle;
use App\Services\Garaz\ConcernAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcernAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicable_to_filters_by_vehicle_type(): void
    {
        MaintenanceConcern::create([
            'name' => 'Car-only check',
            'trigger_type' => ConcernTriggerEnum::TIME,
            'vehicle_type_match' => VehicleTypeEnum::CAR->value,
            'is_active' => true,
        ]);
        MaintenanceConcern::create([
            'name' => 'Bicycle-only check',
            'trigger_type' => ConcernTriggerEnum::TIME,
            'vehicle_type_match' => VehicleTypeEnum::BICYCLE->value,
            'is_active' => true,
        ]);
        MaintenanceConcern::create([
            'name' => 'Universal',
            'trigger_type' => ConcernTriggerEnum::TIME,
            'vehicle_type_match' => null,
            'is_active' => true,
        ]);

        $car = Vehicle::factory()->car()->create();

        $names = MaintenanceConcern::applicableTo($car)->pluck('name')->all();

        $this->assertContains('Car-only check', $names);
        $this->assertContains('Universal', $names);
        $this->assertNotContains('Bicycle-only check', $names);
    }

    public function test_applicable_to_filters_by_engine_code_when_specified(): void
    {
        MaintenanceConcern::create([
            'name' => 'B14XFT timing chain',
            'trigger_type' => ConcernTriggerEnum::MILEAGE,
            'engine_code_match' => 'B14XFT',
            'is_active' => true,
        ]);

        $astraK = Vehicle::factory()->car()->create();
        CarSpec::create(['vehicle_id' => $astraK->id, 'engine_code' => 'B14XFT', 'fuel_type' => FuelTypeEnum::PETROL]);

        $unrelatedCar = Vehicle::factory()->car()->create();
        CarSpec::create(['vehicle_id' => $unrelatedCar->id, 'engine_code' => 'A20DTH', 'fuel_type' => FuelTypeEnum::DIESEL]);

        $this->assertContains('B14XFT timing chain', MaintenanceConcern::applicableTo($astraK->fresh())->pluck('name')->all());
        $this->assertNotContains('B14XFT timing chain', MaintenanceConcern::applicableTo($unrelatedCar->fresh())->pluck('name')->all());
    }

    public function test_inactive_concerns_excluded(): void
    {
        MaintenanceConcern::create([
            'name' => 'Disabled',
            'trigger_type' => ConcernTriggerEnum::TIME,
            'is_active' => false,
        ]);

        $vehicle = Vehicle::factory()->car()->create();

        $this->assertEmpty(MaintenanceConcern::applicableTo($vehicle->fresh())->get());
    }

    public function test_starting_assessment_creates_one_result_per_check(): void
    {
        $vehicle = Vehicle::factory()->car()->create();
        $concern = $this->makeConcernWith3Checks();

        $assessment = app(ConcernAssessmentService::class)->start($vehicle, $concern, $vehicle->user);

        $this->assertEquals(3, $assessment->results->count());
        $this->assertTrue($assessment->results->every(fn ($r) => $r->outcome === CheckOutcomeEnum::PENDING));
        $this->assertEquals(AssessmentVerdictEnum::OPEN, $assessment->verdict);
    }

    public function test_finalize_returns_clear_when_all_pass(): void
    {
        $assessment = $this->openAssessmentWithResults([CheckOutcomeEnum::PASS, CheckOutcomeEnum::PASS, CheckOutcomeEnum::PASS]);

        $final = app(ConcernAssessmentService::class)->finalize($assessment);

        $this->assertEquals(AssessmentVerdictEnum::CLEAR, $final->verdict);
        $this->assertNotNull($final->closed_at);
    }

    public function test_finalize_returns_shop_when_any_fail(): void
    {
        $assessment = $this->openAssessmentWithResults([CheckOutcomeEnum::PASS, CheckOutcomeEnum::FAIL, CheckOutcomeEnum::PASS]);

        $final = app(ConcernAssessmentService::class)->finalize($assessment);

        $this->assertEquals(AssessmentVerdictEnum::SHOP, $final->verdict);
    }

    public function test_finalize_returns_monitor_when_uncertain_present_no_fail(): void
    {
        $assessment = $this->openAssessmentWithResults([CheckOutcomeEnum::PASS, CheckOutcomeEnum::UNCERTAIN]);

        $final = app(ConcernAssessmentService::class)->finalize($assessment);

        $this->assertEquals(AssessmentVerdictEnum::MONITOR, $final->verdict);
        $this->assertNotNull($final->next_due_at);
    }

    public function test_clear_verdict_records_savings_from_concern_cost_range(): void
    {
        $assessment = $this->openAssessmentWithResults(
            outcomes: [CheckOutcomeEnum::PASS, CheckOutcomeEnum::PASS],
            shopCostMin: 60,
            shopCostMax: 100,
        );

        $final = app(ConcernAssessmentService::class)->finalize($assessment);

        $this->assertEquals(AssessmentVerdictEnum::CLEAR, $final->verdict);
        $this->assertEquals(80.00, (float) $final->savings_eur);
    }

    public function test_clear_verdict_computes_next_due_from_concern_intervals(): void
    {
        $assessment = $this->openAssessmentWithResults(
            outcomes: [CheckOutcomeEnum::PASS],
            shopCostMin: 0,
            shopCostMax: 0,
            recheckAfterDays: 180,
            recheckAfterKm: 10_000,
        );
        $assessment->vehicle->update(['current_odometer_km' => 135_000]);

        $final = app(ConcernAssessmentService::class)->finalize($assessment->fresh());

        $this->assertNotNull($final->next_due_at);
        $this->assertEquals(145_000, $final->next_due_km);
    }

    public function test_assessment_cascades_results_on_delete(): void
    {
        $vehicle = Vehicle::factory()->car()->create();
        $concern = $this->makeConcernWith3Checks();

        $assessment = app(ConcernAssessmentService::class)->start($vehicle, $concern);

        $assessment->delete();

        $this->assertDatabaseCount('assessment_check_results', 0);
    }

    public function test_assessment_query_scoped_to_vehicle_owner_in_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceVehicle = Vehicle::factory()->create(['user_id' => $alice->id]);
        $bobVehicle = Vehicle::factory()->create(['user_id' => $bob->id]);

        $concern = $this->makeConcernWith3Checks();

        app(ConcernAssessmentService::class)->start($aliceVehicle, $concern, $alice);
        app(ConcernAssessmentService::class)->start($bobVehicle, $concern, $bob);

        $this->actingAs($alice);
        $aliceVisible = ConcernAssessmentResource::getEloquentQuery()->count();

        $this->assertEquals(1, $aliceVisible);
    }

    private function makeConcernWith3Checks(): MaintenanceConcern
    {
        $concern = MaintenanceConcern::create([
            'name' => 'Test concern',
            'trigger_type' => ConcernTriggerEnum::TIME,
            'is_active' => true,
        ]);

        foreach (range(1, 3) as $i) {
            ConcernCheck::create([
                'maintenance_concern_id' => $concern->id,
                'order' => $i,
                'name' => "Check {$i}",
                'input_type' => ConcernCheckInputEnum::CHOICE,
            ]);
        }

        return $concern->fresh();
    }

    private function openAssessmentWithResults(
        array $outcomes,
        ?float $shopCostMin = null,
        ?float $shopCostMax = null,
        ?int $recheckAfterDays = null,
        ?int $recheckAfterKm = null,
    ): ConcernAssessment {
        $vehicle = Vehicle::factory()->car()->create();
        $concern = MaintenanceConcern::create([
            'name' => 'Cost concern',
            'trigger_type' => ConcernTriggerEnum::TIME,
            'is_active' => true,
            'shop_diagnostic_cost_min_eur' => $shopCostMin,
            'shop_diagnostic_cost_max_eur' => $shopCostMax,
            'recheck_after_days' => $recheckAfterDays,
            'recheck_after_km' => $recheckAfterKm,
        ]);

        foreach ($outcomes as $i => $outcome) {
            ConcernCheck::create([
                'maintenance_concern_id' => $concern->id,
                'order' => $i + 1,
                'name' => "Step {$i}",
                'input_type' => ConcernCheckInputEnum::CHOICE,
            ]);
        }

        $assessment = app(ConcernAssessmentService::class)->start($vehicle, $concern->fresh());

        foreach ($assessment->results as $i => $result) {
            $result->update(['outcome' => $outcomes[$i]]);
        }

        return $assessment->fresh();
    }
}
