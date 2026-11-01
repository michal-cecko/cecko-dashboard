<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\ExercisePresenter;
use App\Models\Stride\Equipment;
use App\Models\Stride\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only exercise library (the shared catalogue plus the user's own
 * exercises) + equipment catalogue. Optionally filtered by category or a
 * free-text query. Response shape mirrors the prototype's
 * STRIDE_LIBRARY / STRIDE_EQUIPMENT data so the React port is a drop-in.
 */
class LibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $exercises = Exercise::query()
            ->visibleTo($request->user()->id)
            ->when($data['category'] ?? null, fn ($q, $category) => $q->inCategory($category))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('name_sk', 'like', "%{$term}%")
            ))
            ->orderBy('category')
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map(ExercisePresenter::payload(...));

        return response()->json([
            'exercises' => $exercises,
            'categories' => $exercises->pluck('category')->unique()->values(),
        ]);
    }

    public function equipment(): JsonResponse
    {
        $groups = Equipment::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->groupBy('group')
            ->map(fn ($items, $group) => [
                'group' => $group,
                'items' => $items->map(fn (Equipment $e) => ['key' => $e->key, 'name' => $e->name])->values(),
            ])
            ->values();

        return response()->json(['groups' => $groups]);
    }
}
