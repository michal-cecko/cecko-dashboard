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
     * "I'll do it tomorrow": push a session one day out. A today/past session
     * moves to tomorrow; a future planned one shifts a day from its own date.
     * Works on skipped sessions too — that's the Reschedule action reviving one.
     */
    public function postpone(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        abort_unless(in_array($session->status, ['today', 'planned', 'skipped'], true), 422);

        $target = $session->scheduled_date !== null && $session->scheduled_date->isAfter(today())
            ? $session->scheduled_date->copy()->addDay()
            : today()->addDay();

        $session->forceFill([
            'status' => 'planned',
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
