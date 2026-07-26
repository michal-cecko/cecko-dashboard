<?php

namespace App\Http\Controllers\Stride;

use App\Enums\Stride\SetMetric;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\SessionPresenter;
use App\Models\Stride\ExerciseSet;
use App\Models\Stride\Session;
use App\Services\Stride\SessionVolume;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Active-session player: read the full session, start/complete it, and log
 * individual sets. The AI "adjust on the fly" flow arrives in Phase 2.
 */
class SessionController extends Controller
{
    public function show(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return response()->json(['session' => SessionPresenter::full($session)]);
    }

    public function start(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $session->forceFill([
            'status' => 'today',
            'started_at' => $session->started_at ?? now(),
        ])->save();

        return response()->json(['session' => SessionPresenter::full($session)]);
    }

    public function complete(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $data = $request->validate([
            'rpe' => ['nullable', 'numeric', 'between:0,10'],
            'notes' => ['nullable', 'string'],
        ]);

        $session->forceFill([
            'status' => 'done',
            'completed_at' => now(),
            'rpe' => $data['rpe'] ?? $session->rpe,
            'notes' => $data['notes'] ?? $session->notes,
            'volume_kg' => SessionVolume::recompute($session),
        ])->save();

        return response()->json(['session' => SessionPresenter::full($session->fresh())]);
    }

    /**
     * Revert a completed session back to "not trained yet" — undo a mistaken (or
     * coach-logged) completion. Only current or future sessions can be reverted;
     * a past session is history and stays done. Clears the logged sets so the
     * session reads fresh.
     */
    public function uncomplete(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        abort_unless($session->status === 'done', 422, 'Only a completed session can be reverted.');
        abort_unless(
            $session->scheduled_date !== null && ! $session->scheduled_date->isBefore(today()),
            422,
            "Only today's or an upcoming session can be reverted to not-done.",
        );

        // Wipe the logged sets (done flag, actuals, metric rows) so nothing reads
        // as trained.
        $session->load('exercises.sets');
        foreach ($session->exercises as $exercise) {
            foreach ($exercise->sets as $set) {
                $set->metricValues()->delete();
                $set->forceFill(['is_done' => false, 'actual_reps' => null, 'actual_kg' => null])->save();
            }
        }

        $session->forceFill([
            'status' => $session->scheduled_date->isToday() ? 'today' : 'planned',
            'completed_at' => null,
            'started_at' => null,
            'rpe' => null,
            'duration_min' => 0,
            'volume_kg' => 0,
        ])->save();

        return response()->json(['session' => SessionPresenter::full($session->fresh())]);
    }

    /**
     * Manually skip an upcoming session (with an optional reason) instead of
     * waiting for the nightly roll to mark it skipped with no context.
     */
    public function skip(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(in_array($session->status, ['today', 'planned'], true), 422);

        $session->forceFill([
            'status' => 'skipped',
            'skip_reason' => $data['reason'] ?? null,
        ])->save();

        return response()->json(['session' => SessionPresenter::full($session)]);
    }

    /**
     * Move a session to another day. The app sends the date the athlete picked
     * ('date'); without one this stays the old "I'll do it tomorrow" shortcut —
     * a today/past session moves to tomorrow, a future planned one shifts a day
     * from its own date. Works on skipped sessions too (Reschedule revives one).
     */
    public function postpone(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        abort_unless(in_array($session->status, ['today', 'planned', 'skipped'], true), 422);

        $target = isset($data['date'])
            ? Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay()
            : ($session->scheduled_date !== null && $session->scheduled_date->isAfter(today())
                ? $session->scheduled_date->copy()->addDay()
                : today()->addDay());

        $session->forceFill([
            // Moving it back onto today makes it today's session again.
            'status' => $target->isToday() ? 'today' : 'planned',
            'scheduled_date' => $target,
            'skip_reason' => null,
        ])->save();

        return response()->json(['session' => SessionPresenter::full($session)]);
    }

    public function logSet(Request $request, Session $session, ExerciseSet $set): JsonResponse
    {
        $this->authorizeSession($request, $session);
        abort_unless($set->sessionExercise->session_id === $session->id, 404);

        $data = $request->validate([
            'is_done' => ['nullable', 'boolean'],
            'actual_reps' => ['nullable', 'integer', 'min:0'],
            'actual_kg' => ['nullable', 'numeric', 'min:0'],
            'metrics' => ['nullable', 'array'],
            'metrics.*' => ['numeric', 'min:0'],
        ]);

        // Metric-keyed log (reps/seconds/weight_kg/band_kg/…): one row per set +
        // metric; unknown keys are dropped. reps/weight_kg are mirrored into the
        // legacy actual_* columns so SessionVolume::recompute stays correct.
        foreach ($data['metrics'] ?? [] as $key => $value) {
            if (SetMetric::tryFrom((string) $key) === null) {
                continue;
            }
            $set->metricValues()->updateOrCreate(['metric' => $key], ['value' => $value]);
            if ($key === SetMetric::REPS->value) {
                $data['actual_reps'] = $data['actual_reps'] ?? (int) $value;
            }
            if ($key === SetMetric::WEIGHT_KG->value) {
                $data['actual_kg'] = $data['actual_kg'] ?? $value;
            }
        }
        unset($data['metrics']);

        $set->fill($data)->save();

        return response()->json(['session' => SessionPresenter::full($session->fresh())]);
    }

    private function authorizeSession(Request $request, Session $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 404);
    }
}
