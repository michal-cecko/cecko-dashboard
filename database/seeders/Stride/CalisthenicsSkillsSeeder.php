<?php

namespace Database\Seeders\Stride;

use App\Models\Stride\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Additive calisthenics skills catalog merged from four curated sources:
 * the WSWCF freestyle Code of Points (docs/freestyle-elements-code-of-points-wswcf-2.xlsx,
 * scoring ignored — used as a skills list), docs/stride-exercise-additions.md,
 * dieringe.com/blog/calisthenics-skills and thegravgear.com's skill list, plus
 * standard regression-ladder fills (planche lean, frog stand, band variants…).
 *
 * Taxonomy: `calisthenics` = programmable basics, `weighted calisthenics` = loaded
 * bodyweight lifts, `freestyle calisthenics` = skills sectioned by tag —
 * Static (holds), Strength Dynamic (skill reps), Dynamic (bar tricks, group null).
 *
 * IDEMPOTENT / NON-DESTRUCTIVE: firstOrCreate keyed on slug — re-running only
 * inserts missing rows and never overwrites an existing or edited exercise.
 */
class CalisthenicsSkillsSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/calisthenics_skills.json';
        $rows = json_decode((string) file_get_contents($path), true) ?? [];

        $created = 0;
        foreach ($rows as $row) {
            $exercise = Exercise::firstOrCreate(
                ['slug' => Str::slug($row['name'])],
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
                    'description' => $row['description'] ?? null,
                    'source_url' => $row['source_url'] ?? null,
                ],
            );

            if ($exercise->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command?->info("CalisthenicsSkillsSeeder: {$created} new / ".count($rows).' total in catalog.');
    }
}
