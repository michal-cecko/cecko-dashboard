<?php

namespace Database\Factories\Garaz;

use App\Enums\Garaz\VehicleDocumentTypeEnum;
use App\Models\Garaz\Vehicle;
use App\Models\Garaz\VehicleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VehicleDocument> */
class VehicleDocumentFactory extends Factory
{
    protected $model = VehicleDocument::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'type' => fake()->randomElement(VehicleDocumentTypeEnum::cases()),
            'label' => fake()->words(3, true),
            'issued_at' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+2 years'),
        ];
    }

    public function forVehicle(?Vehicle $vehicle = null): static
    {
        return $this->state(fn () => [
            'vehicle_id' => $vehicle?->id ?? Vehicle::factory(),
        ]);
    }

    public function stk(): static
    {
        return $this->state(fn () => ['type' => VehicleDocumentTypeEnum::STK]);
    }

    public function pzp(): static
    {
        return $this->state(fn () => ['type' => VehicleDocumentTypeEnum::INSURANCE_PZP]);
    }
}
