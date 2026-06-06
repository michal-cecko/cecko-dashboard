<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goals = Goal::ownedBy($request->user())
            ->orderBy('sort')
            ->orderByDesc('progress')
            ->get();

        return response()->json(['goals' => $goals->map($this->payload(...))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $goal = Goal::create([
            'user_id' => $request->user()->id,
            ...$this->validateData($request),
        ]);

        return response()->json(['goal' => $this->payload($goal)], 201);
    }

    public function update(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $goal->update($this->validateData($request, partial: true));

        return response()->json(['goal' => $this->payload($goal)]);
    }

    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $goal->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'progress' => ['nullable', 'numeric', 'between:0,1'],
            'current_label' => ['nullable', 'string', 'max:255'],
            'target_label' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:16'],
            'is_achieved' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer'],
        ]);
    }

    private function payload(Goal $goal): array
    {
        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'category' => $goal->category,
            'progress' => $goal->progress,
            'current' => $goal->current_label,
            'target' => $goal->target_label,
            'deadline' => $goal->deadline?->toDateString(),
            'color' => $goal->color,
            'is_achieved' => $goal->is_achieved,
        ];
    }
}
