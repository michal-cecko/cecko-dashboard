<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\ExercisePresenter;
use App\Http\Presenters\Stride\SessionPresenter;
use App\Models\Stride\Exercise;
use App\Models\Stride\Routine;
use App\Models\Stride\Session;
use App\Services\Stride\RoutineSteps;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Interval timers for classes the user leads (yoga, pilates, …), and the log of
 * finished runs. Responses use the `{data: …}` wrapper the app's routine screens
 * read.
 */
class RoutineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $routines = Routine::ownedBy($request->user())->orderByDesc('updated_at')->orderByDesc('id')->get();

        return response()->json(['data' => $routines->map(fn (Routine $routine) => [
            'id' => $routine->id,
            'title' => $routine->title,
            'kind' => $routine->kind,
            'total_seconds' => $routine->total_seconds,
            'step_count' => $routine->step_count,
        ])->values()]);
    }

    public function show(Request $request, Routine $routine): JsonResponse
    {
        $this->authorizeRoutine($request, $routine);

        return response()->json(['data' => $this->detail($routine)]);
    }

    public function store(Request $request): JsonResponse
    {
        $routine = Routine::create([
            'user_id' => $request->user()->id,
            ...$this->validateRoutine($request),
        ]);

        return response()->json(['data' => $this->detail($routine)], 201);
    }

    public function update(Request $request, Routine $routine): JsonResponse
    {
        $this->authorizeRoutine($request, $routine);

        $routine->update($this->validateRoutine($request));

        return response()->json(['data' => $this->detail($routine)]);
    }

    public function destroy(Request $request, Routine $routine): Response
    {
        $this->authorizeRoutine($request, $routine);

        $routine->delete();

        return response()->noContent();
    }

    public function duplicate(Request $request, Routine $routine): JsonResponse
    {
        $this->authorizeRoutine($request, $routine);

        $copy = $routine->replicate();
        $copy->title = mb_substr($routine->title, 0, 113).' (copy)';
        $copy->save();

        return response()->json(['data' => $this->detail($copy)], 201);
    }

    /**
     * Log a finished (or ended early) run as a done session. The app retries
     * failed uploads, so a repeated client_id returns the first session.
     *
     * scheduled_date is the date in the offset started_at was sent with, so the
     * app has to send local time: a UTC "Z" time gives the UTC day. The duration
     * comes from elapsed_seconds, because started..completed includes pauses.
     */
    public function logRun(Request $request, Routine $routine): JsonResponse
    {
        $this->authorizeRoutine($request, $routine);

        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'started_at' => ['required', 'date', Session::CLIENT_TIMESTAMP_RULE],
            'completed_at' => ['required', 'date', Session::CLIENT_TIMESTAMP_RULE, 'after_or_equal:started_at'],
            'elapsed_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'completed' => ['required', 'boolean'],
        ]);

        $startedAt = CarbonImmutable::parse($data['started_at']);

        $session = Session::firstOrCreate(
            ['user_id' => $request->user()->id, 'client_id' => $data['client_id']],
            [
                'routine_id' => $routine->id,
                'kind' => 'Routine',
                'title' => $routine->title,
                'status' => 'done',
                'scheduled_date' => $startedAt->toDateString(),
                'started_at' => $startedAt->utc(),
                'completed_at' => CarbonImmutable::parse($data['completed_at'])->utc(),
                'duration_min' => max(1, (int) round($data['elapsed_seconds'] / 60)),
                'volume_kg' => 0,
                'ended_early' => ! $data['completed'],
            ],
        );

        return response()->json(['data' => SessionPresenter::summary($session)], $session->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * The app shows the 422 message as is, so the messages stay English whatever
     * APP_LOCALE is.
     *
     * @return array{title: string, kind: string, steps: array, total_seconds: int, step_count: int}
     */
    private function validateRoutine(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', Rule::in(Routine::KINDS)],
        ], [
            'title.max' => 'The title can be at most 120 characters.',
            'title' => 'A routine needs a title.',
            'kind' => 'The kind must be one of: '.implode(', ', Routine::KINDS).'.',
        ]);

        $steps = RoutineSteps::validate($request->input('steps'), $request->user()->id);

        return [
            ...$data,
            'steps' => $steps,
            'total_seconds' => RoutineSteps::totalSeconds($steps),
            'step_count' => RoutineSteps::count($steps),
        ];
    }

    private function detail(Routine $routine): array
    {
        $exercises = Exercise::query()
            ->visibleTo($routine->user_id)
            ->whereKey(RoutineSteps::exerciseIds($routine->steps))
            ->get();

        return [
            'id' => $routine->id,
            'title' => $routine->title,
            'kind' => $routine->kind,
            'steps' => $routine->steps,
            'total_seconds' => $routine->total_seconds,
            'step_count' => $routine->step_count,
            'exercises' => $exercises->map(ExercisePresenter::payload(...))->values(),
        ];
    }

    private function authorizeRoutine(Request $request, Routine $routine): void
    {
        abort_unless($routine->user_id === $request->user()->id, 404);
    }
}
