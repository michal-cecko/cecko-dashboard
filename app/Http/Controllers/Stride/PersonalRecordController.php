<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\Exercise;
use App\Models\Stride\PersonalRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personal records — the athlete's current/past bests per exercise. Type-aware:
 * the `metrics` JSON holds whatever the exercise's metric_type needs.
 */
class PersonalRecordController extends Controller
{
    private const METRIC_TYPES = ['load', 'reps', 'hold', 'run', 'sprint', 'machine'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['exercise_id' => ['nullable', 'integer']]);

        $records = PersonalRecord::ownedBy($request->user())
            ->when($data['exercise_id'] ?? null, fn ($q, $id) => $q->where('exercise_id', $id))
            ->orderByDesc('achieved_on')
            ->orderByDesc('id')
            ->get();

        return response()->json(['records' => $records->map($this->payload(...))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $record = PersonalRecord::create([
            'user_id' => $request->user()->id,
            ...$this->validateData($request),
        ]);

        return response()->json(['record' => $this->payload($record)], 201);
    }

    public function update(Request $request, PersonalRecord $personalRecord): JsonResponse
    {
        abort_unless($personalRecord->user_id === $request->user()->id, 404);

        $personalRecord->update($this->validateData($request, partial: true));

        return response()->json(['record' => $this->payload($personalRecord)]);
    }

    public function destroy(Request $request, PersonalRecord $personalRecord): JsonResponse
    {
        abort_unless($personalRecord->user_id === $request->user()->id, 404);

        $personalRecord->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'exercise_id' => ['nullable', 'integer', 'exists:stride_exercises,id'],
            'label' => [$required, 'string', 'max:120'],
            'metric_type' => [$required, 'string', 'in:'.implode(',', self::METRIC_TYPES)],
            'metrics' => [$required, 'array'],
            'metrics.weight' => ['nullable', 'numeric', 'between:0,1000'],
            'metrics.reps' => ['nullable', 'integer', 'between:0,10000'],
            'metrics.seconds' => ['nullable', 'integer', 'between:0,86400'],
            'metrics.distance_m' => ['nullable', 'numeric', 'between:0,1000000'],
            'metrics.calories' => ['nullable', 'integer', 'between:0,100000'],
            'metrics.watts' => ['nullable', 'integer', 'between:0,5000'],
            'achieved_on' => ['nullable', 'date', 'before_or_equal:today'],
            'form_quality' => ['nullable', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Default the label from the exercise when one is linked and none was given.
        if (! isset($data['label']) && isset($data['exercise_id'])) {
            $data['label'] = Exercise::query()->whereKey($data['exercise_id'])->value('name') ?? 'Record';
        }

        return $data;
    }

    private function payload(PersonalRecord $record): array
    {
        return [
            'id' => $record->id,
            'exercise_id' => $record->exercise_id,
            'label' => $record->label,
            'metric_type' => $record->metric_type,
            'metrics' => $record->metrics ?? [],
            'display' => $record->display(),
            'achieved_on' => $record->achieved_on?->toDateString(),
            'form_quality' => $record->form_quality,
            'note' => $record->note,
        ];
    }
}
