<?php

namespace Database\Factories;

use App\Models\Songs\SongGenre;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SongGenre> */
class SongGenreFactory extends Factory
{
    protected $model = SongGenre::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => fake()->optional()->hexColor(),
        ];
    }
}
