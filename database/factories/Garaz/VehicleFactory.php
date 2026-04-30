<?php

namespace Database\Factories\Garaz;

use App\Enums\Garaz\VehicleTypeEnum;
use App\Models\Common\User;
use App\Models\Garaz\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => VehicleTypeEnum::CAR,
            'nickname' => fake()->words(2, true),
            'make' => fake()->randomElement(['Opel', 'Škoda', 'VW', 'Toyota']),
            'model' => fake()->randomElement(['Astra', 'Octavia', 'Golf', 'Yaris']),
            'year_of_manufacture' => fake()->numberBetween(2010, 2024),
            'current_odometer_km' => fake()->numberBetween(10_000, 200_000),
            'current_odometer_at' => now(),
        ];
    }

    public function car(): static
    {
        return $this->state(fn () => ['type' => VehicleTypeEnum::CAR]);
    }

    public function motorcycle(): static
    {
        return $this->state(fn () => [
            'type' => VehicleTypeEnum::MOTORCYCLE,
            'make' => fake()->randomElement(['Honda', 'Yamaha', 'Kawasaki', 'KTM']),
            'model' => fake()->randomElement(['CBR', 'MT-07', 'Z650', 'Duke']),
        ]);
    }

    public function bicycle(): static
    {
        return $this->state(fn () => [
            'type' => VehicleTypeEnum::BICYCLE,
            'make' => fake()->randomElement(['Trek', 'Cube', 'Giant', 'Specialized']),
            'model' => fake()->randomElement(['X-Caliber', 'Reaction', 'Talon', 'Rockhopper']),
            'license_plate' => null,
        ]);
    }
}
