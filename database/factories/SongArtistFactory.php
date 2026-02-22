<?php

namespace Database\Factories;

use App\Models\Songs\SongArtist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SongArtist> */
class SongArtistFactory extends Factory
{
    protected $model = SongArtist::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
        ];
    }
}
