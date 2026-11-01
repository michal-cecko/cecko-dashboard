<?php

namespace Database\Factories\Support;

use App\Models\Support\SupportContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportContact> */
class SupportContactFactory extends Factory
{
    protected $model = SupportContact::class;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'is_active' => true];
    }
}
