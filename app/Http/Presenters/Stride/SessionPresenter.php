<?php

namespace App\Http\Presenters\Stride;

use App\Models\Stride\ExerciseSet;
use App\Models\Stride\Session;
use App\Models\Stride\SessionExercise;

/**
 * Serialises sessions into the shape the prototype consumes (STRIDE_TODAYS_WORKOUT
 * / STRIDE_WEEK_PLAN). Shared by Home, Plan and Session controllers.
 */
class SessionPresenter
{
    /** A row for week plans, recent activity and block timelines. */
    public static function summary(Session $session): array
    {
        return [
            'id' => $session->id,
            'day' => $session->scheduled_date?->format('D'),
            'date' => $session->scheduled_date?->day,
            'scheduled_date' => $session->scheduled_date?->toDateString(),
            'kind' => $session->kind,
            'title' => $session->title,
            'status' => $session->status,
            'volume' => $session->volume_kg,
            'duration' => $session->duration_min,
            'rpe' => $session->rpe,
            'skip_reason' => $session->skip_reason,
        ];
    }

    /** The full session player payload: exercises + sets. */
    public static function full(Session $session): array
    {
        $session->loadMissing('exercises.sets', 'exercises.exercise');

        return array_merge(self::summary($session), [
            'block_id' => $session->block_id,
            'notes' => $session->notes,
            'started_at' => $session->started_at?->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'exercises' => $session->exercises->map(self::exercise(...))->values(),
        ]);
    }

    private static function exercise(SessionExercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'exercise_id' => $exercise->exercise_id,
            'name' => $exercise->name,
            'tag' => $exercise->tag,
            'note' => $exercise->note,
            'video_cue' => $exercise->video_cue,
            // How this exercise is measured — 'hold' renders seconds (not reps)
            // in the workout player. Falls back to 'load' for unlinked rows.
            'metric_type' => $exercise->exercise?->metric_type ?? 'load',
            'sets' => $exercise->sets->map(self::set(...))->values(),
        ];
    }

    private static function set(ExerciseSet $set): array
    {
        return [
            'id' => $set->id,
            'kind' => $set->kind,
            'reps' => $set->reps,
            'kg' => $set->kg,
            'rest' => $set->rest_sec,
            'is_done' => $set->is_done,
            'actual_reps' => $set->actual_reps,
            'actual_kg' => $set->actual_kg,
        ];
    }
}
