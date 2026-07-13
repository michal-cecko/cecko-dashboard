<?php

namespace App\Services\Stride;

use App\Models\Stride\Exercise;
use App\Models\Stride\SessionExercise;

/**
 * Resolves the catalogue category of a session exercise so the coach can reason
 * about "calisthenics" vs "strength" etc. SessionExercise.exercise_id is often
 * null (the demo seed never sets it, and a swap clears it), so we fall back to a
 * name lookup against the catalogue.
 */
class ExerciseCategory
{
    public static function of(SessionExercise $exercise): ?string
    {
        if ($exercise->exercise_id) {
            $category = Exercise::query()->whereKey($exercise->exercise_id)->value('category');
            if ($category) {
                return $category;
            }
        }

        return Exercise::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($exercise->name))])->value('category');
    }

    /** Does the exercise match a {by:'category'|'name', value} filter? */
    public static function matches(SessionExercise $exercise, string $by, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if ($by === 'category') {
            return self::of($exercise) === mb_strtolower($value);
        }

        return str_contains(self::normalize($exercise->name), self::normalize($value));
    }

    private static function normalize(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s));
    }
}
