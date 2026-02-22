<?php

namespace Database\Factories;

use App\Models\Songs\SongTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SongTag> */
class SongTagFactory extends Factory
{
    protected $model = SongTag::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => fake()->randomElement(['danger', 'gray', 'info', 'success', 'warning']),
        ];
    }
}
