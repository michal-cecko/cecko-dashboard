<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\SessionPresenter;
use App\Models\Stride\ExerciseSet;
use App\Models\Stride\Session;
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
            'volume_kg' => $this->computeVolume($session),
        ])->save();

        return response()->json(['session' => SessionPresenter::full($session->fresh())]);
    }

    public function logSet(Request $request, Session $session, ExerciseSet $set): JsonResponse
    {
        $this->authorizeSession($request, $session);
        abort_unless($set->sessionExercise->session_id === $session->id, 404);

        $data = $request->validate([
            'is_done' => ['nullable', 'boolean'],
            'actual_reps' => ['nullable', 'integer', 'min:0'],
            'actual_kg' => ['nullable', 'numeric', 'min:0'],
        ]);

        $set->fill($data)->save();

        return response()->json(['session' => SessionPresenter::full($session->fresh())]);
    }

    private function authorizeSession(Request $request, Session $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 404);
    }

    /** Sum of logged (or planned) working volume: reps × kg across done sets. */
    private function computeVolume(Session $session): int
    {
        $session->loadMissing('exercises.sets');

        return (int) $session->exercises->flatMap->sets
            ->where('is_done', true)
            ->sum(fn (ExerciseSet $s) => ($s->actual_reps ?? $s->reps) * (float) ($s->actual_kg ?? $s->kg));
    }
}
