<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportOrder;
use App\Models\Support\SupportProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportOrder> */
class SupportOrderFactory extends Factory
{
    protected $model = SupportOrder::class;

    public function definition(): array
    {
        return ['project_id' => SupportProject::factory(), 'kind' => 'quote', 'scope' => fake()->sentence(), 'terms' => [], 'currency' => 'EUR', 'operation_key' => fake()->uuid()];
    }
}
