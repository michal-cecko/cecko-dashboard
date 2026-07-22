<?php

namespace Database\Seeders\Stride;

use App\Models\Stride\Exercise;
use Illuminate\Database\Seeder;

/**
 * One-time cleanup moving a handful of existing catalog rows into the new
 * calisthenics taxonomy (basic / weighted / freestyle with Static|Strength
 * Dynamic|Dynamic sections) and fixing `group` values the plan generator's
 * day-matching never matched (e.g. Push-up had group "Push", but a Push day
 * looks for Chest/Shoulders/Triceps).
 *
 * SURGICAL + IDEMPOTENT: only per-slug UPDATEs on stride_exercises — no
 * deletes, no truncation, no other tables touched.
 */
class RestructureCalisthenicsCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $updates = [
            'front-lever' => ['category' => 'freestyle calisthenics', 'tag' => 'Static'],
            'handstand-hold' => ['category' => 'freestyle calisthenics', 'tag' => 'Static'],
            'chin-up' => ['category' => 'calisthenics'],
            'pull-up-strict' => ['category' => 'calisthenics'],
            'weighted-dips' => ['category' => 'weighted calisthenics'],
            'push-up' => ['group' => 'Chest'],
        ];

        $changed = 0;
        foreach ($updates as $slug => $attributes) {
            $changed += Exercise::query()->where('slug', $slug)->update($attributes);
        }

        $this->command?->info("RestructureCalisthenicsCategoriesSeeder: {$changed} row(s) updated.");
    }
}
