<?php

namespace Database\Factories;

use App\Models\Songs\Song;
use App\Models\Songs\SongGenre;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Song> */
class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'number' => fake()->unique()->numberBetween(1, 9999),
            'lyrics' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'genre_id' => SongGenre::factory(),
            'bpm' => fake()->optional()->numberBetween(60, 200),
        ];
    }
}
