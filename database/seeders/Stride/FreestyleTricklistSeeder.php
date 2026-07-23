<?php

namespace Database\Seeders\Stride;

use App\Models\Stride\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Additive seeder for the freestyle bar-calisthenics tricklist sourced from
 * Instagram @freestyle_tricklist (325 dynamic tricks, each with a self-hosted
 * S3 video + a source credit link).
 *
 * These live in a dedicated `freestyle calisthenics` category and are LIBRARY-ONLY —
 * the plan generator's pool is strength+calisthenics, so it never auto-programs them.
 * They are skill practice, browsed with a tap-to-play demo video.
 *
 * IDEMPOTENT / NON-DESTRUCTIVE: uses firstOrCreate keyed on slug, so re-running
 * only inserts rows that don't exist yet and never overwrites an edited row.
 * Names are AI-derived from each post's caption; the owner renames the uncertain
 * ones afterwards (which then won't be re-touched by this seeder).
 */
class FreestyleTricklistSeeder extends Seeder
{
    public const CATEGORY = 'freestyle calisthenics';

    public function run(): void
    {
        $path = __DIR__.'/data/freestyle_tricklist.json';
        $rows = json_decode((string) file_get_contents($path), true) ?? [];

        $created = 0;
        foreach ($rows as $row) {
            $exercise = Exercise::firstOrCreate(
                ['slug' => Str::slug($row['name'])],
                [
                    'name' => $row['name'],
                    'category' => self::CATEGORY,
                    'group' => null,           // library-only; not day-programmed
                    'tag' => 'Dynamic',
                    'metric_type' => 'reps',
                    'difficulty' => $row['difficulty'],
                    'equipment_label' => 'Freestyle bar',
                    'primary_muscles' => ['Full body'],
                    'secondary_muscles' => [],
                    'video_url' => $row['video_url'] ?? null,
                    'thumbnail_url' => $row['thumbnail_url'] ?? null,
                    'description' => $row['description'] ?? null,
                    'source_url' => $row['source_url'] ?? null,
                ],
            );

            if ($exercise->wasRecentlyCreated) {
                $created++;
            } elseif ($exercise->thumbnail_url === null && ($row['thumbnail_url'] ?? null) !== null) {
                $exercise->update(['thumbnail_url' => $row['thumbnail_url']]);
            }
        }

        $this->command?->info("FreestyleTricklistSeeder: {$created} new / ".count($rows).' total in catalog.');
    }
}
