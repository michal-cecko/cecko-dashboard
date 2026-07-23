<?php

namespace Tests\Feature\Stride;

use App\Models\Stride\Exercise;
use Database\Seeders\Stride\MuscleAndStrengthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StrideMuscleAndStrengthSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    private function dataset(): array
    {
        $path = database_path('seeders/Stride/data/mns_gym_exercises.json');

        return json_decode((string) file_get_contents($path), true) ?? [];
    }

    public function test_seeder_loads_catalog_and_is_idempotent(): void
    {
        $rows = $this->dataset();
        $this->assertNotEmpty($rows, 'mns_gym_exercises.json must not be empty');

        Artisan::call('db:seed', ['--class' => MuscleAndStrengthSeeder::class]);

        $this->assertSame(count($rows), Exercise::count(), 'every dataset row seeded');
        $this->assertSame(count($rows), Exercise::whereNotNull('source_url')->count(), 'every row credits its M&S source page');

        $exercises = Exercise::all();
        $this->assertTrue(
            $exercises->every(fn (Exercise $e): bool => in_array($e->difficulty, ['Beginner', 'Intermediate', 'Advanced'], true)),
            'difficulty is one of the three tiers'
        );
        $this->assertTrue(
            $exercises->every(fn (Exercise $e): bool => in_array($e->tag, ['Compound', 'Isolation'], true)),
            'tag is Compound or Isolation'
        );

        Artisan::call('db:seed', ['--class' => MuscleAndStrengthSeeder::class]);
        $this->assertSame(count($rows), Exercise::count(), 'second run creates no duplicates');
    }

    public function test_backfills_null_fields_on_existing_rows_without_overwriting(): void
    {
        $rows = $this->dataset();
        [$first, $second] = [$rows[0], $rows[1]];

        $curated = Exercise::create([
            'slug' => $first['slug'],
            'name' => 'Curated Name',
            'category' => 'strength',
            'group' => 'Chest',
            'tag' => 'Compound',
            'metric_type' => 'load',
            'difficulty' => 'Intermediate',
            'equipment_label' => 'Barbell + bench',
            'primary_muscles' => ['Chest'],
            'secondary_muscles' => [],
        ]);
        $handEdited = Exercise::create([
            'slug' => $second['slug'],
            'name' => 'Hand Edited',
            'category' => 'strength',
            'group' => 'Back',
            'tag' => 'Compound',
            'metric_type' => 'load',
            'difficulty' => 'Beginner',
            'equipment_label' => 'Barbell',
            'primary_muscles' => ['Back'],
            'secondary_muscles' => [],
            'video_url' => 'https://example.test/custom.mp4',
            'description' => 'My own words.',
            'source_url' => 'https://example.test/origin',
        ]);

        Artisan::call('db:seed', ['--class' => MuscleAndStrengthSeeder::class]);

        $curated->refresh();
        $this->assertSame('Curated Name', $curated->name, 'curated name never overwritten');
        $this->assertSame('Chest', $curated->group, 'curated group never overwritten');
        $this->assertSame($first['source_url'], $curated->source_url, 'null source_url backfilled');
        $this->assertSame($first['video_url'], $curated->video_url, 'null video_url backfilled from dataset');
        $this->assertSame($first['thumbnail_url'], $curated->thumbnail_url, 'null thumbnail_url backfilled from dataset');
        $this->assertSame($first['description'], $curated->description, 'null description backfilled from dataset');

        $handEdited->refresh();
        $this->assertSame('https://example.test/custom.mp4', $handEdited->video_url, 'existing video never overwritten');
        $this->assertSame('My own words.', $handEdited->description, 'existing description never overwritten');
        $this->assertSame('https://example.test/origin', $handEdited->source_url, 'existing source never overwritten');
    }
}
