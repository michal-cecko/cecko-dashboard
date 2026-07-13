<?php

namespace App\Services\Stride;

use App\Models\Stride\ExerciseSet;
use App\Models\Stride\Session;

/**
 * Total load moved for a session. Once any set is logged we report the LOGGED
 * volume (actuals across done sets); before that we report the PLANNED target
 * (Working sets reps×kg) so coach edits to a not-yet-started session still show a
 * sensible number instead of zero.
 */
class SessionVolume
{
    public static function recompute(Session $session): int
    {
        $session->loadMissing('exercises.sets');
        $sets = $session->exercises->flatMap->sets;

        $done = $sets->where('is_done', true);
        if ($done->isNotEmpty()) {
            return (int) $done->sum(fn (ExerciseSet $s) => ($s->actual_reps ?? $s->reps) * (float) ($s->actual_kg ?? $s->kg));
        }

        return (int) $sets->where('kind', 'Working')->sum(fn (ExerciseSet $s) => $s->reps * (float) $s->kg);
    }
}
