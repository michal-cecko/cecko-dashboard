<?php

namespace Database\Seeders\Stride;

use App\Models\Stride\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Representative exercise library, flattened from the prototype's nested
 * STRIDE_LIBRARY tree (claude-designed-templates/data.jsx). This is a curated
 * starter set covering each strength group plus a few other categories; the
 * full ~140-exercise catalogue import is a follow-up data task.
 *
 * Each row: [name, category, group, tag, difficulty, equipment, primary, secondary?, metric_type?]
 * metric_type defaults from the category (see defaultMetricType); pass an explicit
 * 9th element to override (e.g. a calisthenics HOLD).
 */
class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->exercises() as $row) {
            [$name, $category, $group, $tag, $difficulty, $equipment, $primary] = $row;
            $secondary = $row[7] ?? [];
            $metricType = $row[8] ?? $this->defaultMetricType($category);

            Exercise::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => $category,
                    'group' => $group,
                    'tag' => $tag,
                    'metric_type' => $metricType,
                    'difficulty' => $difficulty,
                    'equipment_label' => $equipment,
                    'primary_muscles' => (array) $primary,
                    'secondary_muscles' => (array) $secondary,
                ],
            );
        }
    }

    /** How an exercise's personal records are measured, defaulted from its category. */
    private function defaultMetricType(string $category): string
    {
        return match ($category) {
            'cardio' => 'run',
            'conditioning' => 'machine',
            'calisthenics', 'mobility' => 'reps',
            default => 'load', // strength
        };
    }

    /** @return array<int, array<int, mixed>> */
    private function exercises(): array
    {
        return [
            // Chest
            ['Barbell Bench Press', 'strength', 'Chest', 'Compound', 'Intermediate', 'Barbell + bench', 'Chest', ['Triceps', 'Front delts']],
            ['Incline Bench Press', 'strength', 'Chest', 'Compound', 'Intermediate', 'Barbell + incline bench', 'Upper chest', ['Front delts']],
            ['Close-Grip Bench Press', 'strength', 'Chest', 'Compound', 'Intermediate', 'Barbell + bench', 'Triceps', ['Chest']],
            ['Flat DB Press', 'strength', 'Chest', 'Compound', 'Beginner', 'Dumbbells + bench', 'Chest', ['Triceps']],
            ['Incline DB Press', 'strength', 'Chest', 'Compound', 'Beginner', 'Dumbbells + incline bench', 'Upper chest'],
            ['DB Fly', 'strength', 'Chest', 'Isolation', 'Beginner', 'Dumbbells + bench', 'Chest'],
            ['Cable Fly — High to Low', 'strength', 'Chest', 'Isolation', 'Beginner', 'Cables', 'Lower chest'],
            ['Pec Deck', 'strength', 'Chest', 'Isolation', 'Beginner', 'Machine', 'Chest'],

            // Shoulders
            ['Overhead Press (Standing)', 'strength', 'Shoulders', 'Compound', 'Intermediate', 'Barbell', 'Front delts', ['Triceps', 'Core']],
            ['Seated DB Shoulder Press', 'strength', 'Shoulders', 'Compound', 'Beginner', 'Dumbbells + bench', 'Delts'],
            ['Arnold Press', 'strength', 'Shoulders', 'Compound', 'Intermediate', 'Dumbbells', 'All three delts'],
            ['DB Lateral Raise', 'strength', 'Shoulders', 'Isolation', 'Beginner', 'Dumbbells', 'Side delts'],
            ['Face Pull', 'strength', 'Shoulders', 'Isolation', 'Beginner', 'Cable + rope', 'Rear delts', ['Upper traps']],

            // Triceps
            ['Tricep Rope Pushdown', 'strength', 'Triceps', 'Isolation', 'Beginner', 'Cable + rope', 'Triceps'],
            ['Skullcrusher', 'strength', 'Triceps', 'Isolation', 'Intermediate', 'EZ-bar + bench', 'Long head'],
            ['Weighted Dips', 'strength', 'Triceps', 'Compound', 'Intermediate', 'Dip bar + belt', 'Triceps', ['Chest']],

            // Back
            ['Pull-up (Strict)', 'strength', 'Back', 'Compound', 'Intermediate', 'Bar', 'Lats', [], 'reps'],
            ['Chin-up', 'strength', 'Back', 'Compound', 'Beginner', 'Bar', 'Lats', ['Biceps'], 'reps'],
            ['Lat Pulldown', 'strength', 'Back', 'Compound', 'Beginner', 'Cable', 'Lats'],
            ['Barbell Row', 'strength', 'Back', 'Compound', 'Intermediate', 'Barbell', 'Mid back'],
            ['Seated Cable Row', 'strength', 'Back', 'Compound', 'Beginner', 'Cable + V-handle', 'Mid back'],
            ['Single-Arm DB Row', 'strength', 'Back', 'Compound', 'Beginner', 'Dumbbell + bench', 'Lats'],

            // Biceps
            ['Barbell Curl', 'strength', 'Biceps', 'Isolation', 'Beginner', 'Barbell', 'Biceps'],
            ['Incline DB Curl', 'strength', 'Biceps', 'Isolation', 'Beginner', 'Dumbbells + incline bench', 'Long head'],
            ['Hammer Curl', 'strength', 'Biceps', 'Isolation', 'Beginner', 'Dumbbells', 'Brachialis'],

            // Legs
            ['Back Squat', 'strength', 'Legs', 'Compound', 'Intermediate', 'Barbell + rack', 'Quads', ['Glutes', 'Core']],
            ['Front Squat', 'strength', 'Legs', 'Compound', 'Advanced', 'Barbell', 'Quads'],
            ['Bulgarian Split Squat', 'strength', 'Legs', 'Compound', 'Intermediate', 'DBs + bench', 'Quads', ['Glutes']],
            ['Leg Press', 'strength', 'Legs', 'Compound', 'Beginner', 'Machine', 'Quads'],
            ['Romanian Deadlift', 'strength', 'Legs', 'Compound', 'Intermediate', 'Barbell', 'Hamstrings', ['Glutes']],
            ['Leg Extension', 'strength', 'Legs', 'Isolation', 'Beginner', 'Machine', 'Quads'],

            // Calisthenics
            ['Push-up', 'calisthenics', 'Push', 'Compound', 'Beginner', 'Bodyweight', 'Chest', ['Triceps']],
            ['Pistol Squat', 'calisthenics', 'Legs', 'Compound', 'Advanced', 'Bodyweight', 'Quads', ['Balance']],
            ['Hanging Leg Raise', 'calisthenics', 'Core', 'Isolation', 'Intermediate', 'Pull-up bar', 'Abs'],

            // Calisthenics — holds / isometrics (metric_type = hold)
            ['Front Lever', 'calisthenics', 'Back', 'Compound', 'Advanced', 'Pull-up bar', 'Lats', ['Core'], 'hold'],
            ['Plank', 'calisthenics', 'Core', 'Isolation', 'Beginner', 'Bodyweight', 'Abs', [], 'hold'],
            ['L-sit', 'calisthenics', 'Core', 'Compound', 'Intermediate', 'Parallettes', 'Abs', ['Hip flexors'], 'hold'],
            ['Handstand Hold', 'calisthenics', 'Shoulders', 'Compound', 'Advanced', 'Bodyweight', 'Shoulders', ['Core'], 'hold'],

            // Conditioning / Cardio
            ['Assault Bike Intervals', 'conditioning', 'Full body', 'Conditioning', 'Intermediate', 'Assault bike', 'Cardiovascular'],
            ['Stationary Bike', 'conditioning', 'Full body', 'Conditioning', 'Beginner', 'Stationary bike', 'Cardiovascular'],
            ['Rowing Intervals', 'conditioning', 'Full body', 'Conditioning', 'Beginner', 'Rowing machine', 'Cardiovascular', ['Back', 'Legs']],
            ['Easy Zone 2 Run', 'cardio', 'Running', 'Cardio', 'Beginner', 'Open running area', 'Cardiovascular'],
            ['Sprint Intervals', 'cardio', 'Running', 'Cardio', 'Intermediate', 'Open running area', 'Cardiovascular', [], 'sprint'],

            // Mobility
            ['Band Pull-Apart', 'mobility', 'Shoulders', 'Mobility', 'Beginner', 'Resistance bands', 'Rear delts'],
            ['90/90 Hip Switch', 'mobility', 'Hips', 'Mobility', 'Beginner', 'Bodyweight', 'Hips'],
        ];
    }
}
