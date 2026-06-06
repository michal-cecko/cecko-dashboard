<?php

namespace Database\Seeders\Stride;

use Illuminate\Database\Seeder;

/**
 * Seeds Stride's reference catalogue (equipment, exercises, official spots).
 * Idempotent — safe to re-run. Registered from DatabaseSeeder.
 */
class StrideSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EquipmentSeeder::class,
            ExerciseSeeder::class,
            SpotSeeder::class,
        ]);
    }
}
