<?php

namespace Tests\Feature\Garaz;

use App\Enums\Garaz\VehicleDocumentTypeEnum;
use App\Models\Garaz\Vehicle;
use App\Models\Garaz\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VehicleDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_attached_to_vehicle_via_relation(): void
    {
        $vehicle = Vehicle::factory()->create();

        $doc = VehicleDocument::create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleDocumentTypeEnum::STK,
            'expires_at' => now()->addYear(),
        ]);

        $this->assertTrue($vehicle->documents()->exists());
        $this->assertEquals($doc->id, $vehicle->documents->first()->id);
    }

    public function test_days_until_expiry_returns_null_when_no_expires_at(): void
    {
        $doc = VehicleDocument::factory()->forVehicle()->create([
            'expires_at' => null,
        ]);

        $this->assertNull($doc->daysUntilExpiry());
        $this->assertEquals('none', $doc->expiryStatus());
    }

    public function test_expiry_status_critical_within_seven_days(): void
    {
        $doc = $this->makeDoc(now()->addDays(3));

        $this->assertEquals(3, $doc->daysUntilExpiry());
        $this->assertEquals('critical', $doc->expiryStatus());
    }

    public function test_expiry_status_warning_within_thirty_days(): void
    {
        $doc = $this->makeDoc(now()->addDays(20));

        $this->assertEquals('warning', $doc->expiryStatus());
    }

    public function test_expiry_status_ok_when_far_future(): void
    {
        $doc = $this->makeDoc(now()->addMonths(6));

        $this->assertEquals('ok', $doc->expiryStatus());
    }

    public function test_expiry_status_expired_when_past(): void
    {
        $doc = $this->makeDoc(now()->subDays(2));

        $this->assertEquals(-2, $doc->daysUntilExpiry());
        $this->assertEquals('expired', $doc->expiryStatus());
    }

    public function test_expiring_soon_scope_includes_only_future_within_window(): void
    {
        $vehicle = Vehicle::factory()->create();

        VehicleDocument::create(['vehicle_id' => $vehicle->id, 'type' => VehicleDocumentTypeEnum::STK, 'expires_at' => now()->addDays(15)]);
        VehicleDocument::create(['vehicle_id' => $vehicle->id, 'type' => VehicleDocumentTypeEnum::EK, 'expires_at' => now()->addDays(45)]);
        VehicleDocument::create(['vehicle_id' => $vehicle->id, 'type' => VehicleDocumentTypeEnum::INSURANCE_PZP, 'expires_at' => now()->subDays(2)]);

        $this->assertEquals(1, VehicleDocument::expiringSoon(30)->count());
        $this->assertEquals(2, VehicleDocument::expiringSoon(60)->count());
        $this->assertEquals(1, VehicleDocument::expired()->count());
    }

    public function test_deleting_vehicle_cascades_to_documents(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleDocument::create(['vehicle_id' => $vehicle->id, 'type' => VehicleDocumentTypeEnum::STK]);
        VehicleDocument::create(['vehicle_id' => $vehicle->id, 'type' => VehicleDocumentTypeEnum::INSURANCE_PZP]);

        $vehicle->delete();

        $this->assertDatabaseCount('vehicle_documents', 0);
    }

    public function test_tracks_expiry_helper_only_true_for_renewal_types(): void
    {
        $this->assertTrue(VehicleDocumentTypeEnum::STK->tracksExpiry());
        $this->assertTrue(VehicleDocumentTypeEnum::EK->tracksExpiry());
        $this->assertTrue(VehicleDocumentTypeEnum::INSURANCE_PZP->tracksExpiry());
        $this->assertTrue(VehicleDocumentTypeEnum::INSURANCE_HAVARIJKA->tracksExpiry());
        $this->assertFalse(VehicleDocumentTypeEnum::SERVICE_BOOK->tracksExpiry());
        $this->assertFalse(VehicleDocumentTypeEnum::INVOICE_RECEIPT->tracksExpiry());
        $this->assertFalse(VehicleDocumentTypeEnum::OTHER->tracksExpiry());
    }

    private function makeDoc(\DateTimeInterface|Carbon $expires): VehicleDocument
    {
        $vehicle = Vehicle::factory()->create();

        return VehicleDocument::create([
            'vehicle_id' => $vehicle->id,
            'type' => VehicleDocumentTypeEnum::STK,
            'expires_at' => $expires,
        ]);
    }
}
