<?php

namespace Database\Seeders\Stride;

use App\Models\Stride\Exercise;
use Illuminate\Database\Seeder;

/**
 * Additive seeder for the gym-exercise catalogue scraped from
 * muscleandstrength.com (~1500 exercises across all muscle groups), each with
 * a self-hosted S3 demo video and a source credit link to the origin page.
 * The scrape/download/upload pipeline lives in tools/mns/ (scrape.py →
 * build_dataset.py → download_videos.sh → upload_s3.sh).
 *
 * IDEMPOTENT / NON-DESTRUCTIVE: uses firstOrCreate keyed on slug, so re-running
 * only inserts rows that don't exist yet and never overwrites an edited row.
 * For rows that already exist (e.g. the curated ExerciseSeeder staples) it
 * additionally fills video_url / description / source_url — but ONLY where the
 * current value is null, so curated data and hand-edited videos are never touched.
 * Videos may be uploaded in batches: rerunning after a new upload backfills the
 * newly available video URLs.
 */
class MuscleAndStrengthSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/mns_gym_exercises.json';
        $rows = json_decode((string) file_get_contents($path), true) ?? [];

        $created = 0;
        $backfilled = 0;
        foreach ($rows as $row) {
            $exercise = Exercise::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'group' => $row['group'],
                    'tag' => $row['tag'],
                    'metric_type' => $row['metric_type'],
                    'difficulty' => $row['difficulty'],
                    'equipment_label' => $row['equipment_label'],
                    'primary_muscles' => $row['primary_muscles'],
                    'secondary_muscles' => $row['secondary_muscles'],
                    'video_url' => $row['video_url'] ?? null,
                    'thumbnail_url' => $row['thumbnail_url'] ?? null,
                    'description' => $row['description'] ?? null,
                    'source_url' => $row['source_url'] ?? null,
                ],
            );

            if ($exercise->wasRecentlyCreated) {
                $created++;

                continue;
            }

            $fillable = array_filter([
                'video_url' => $row['video_url'] ?? null,
                'thumbnail_url' => $row['thumbnail_url'] ?? null,
                'description' => $row['description'] ?? null,
                'source_url' => $row['source_url'] ?? null,
            ], fn (?string $value, string $field): bool => $value !== null && $exercise->{$field} === null, ARRAY_FILTER_USE_BOTH);

            if ($fillable !== []) {
                $exercise->update($fillable);
                $backfilled++;
            }
        }

        $this->command?->info(
            "MuscleAndStrengthSeeder: {$created} new, {$backfilled} backfilled / ".count($rows).' total in catalog.'
        );
    }
}
