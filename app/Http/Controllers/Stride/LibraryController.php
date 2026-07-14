<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\Equipment;
use App\Models\Stride\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only exercise library + equipment catalogue. Optionally filtered by
 * category or a free-text query. Response shape mirrors the prototype's
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
            ->when($data['category'] ?? null, fn ($q, $category) => $q->inCategory($category))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('category')
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map($this->exercisePayload(...));

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

    private function exercisePayload(Exercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'slug' => $exercise->slug,
            'name' => $exercise->name,
            'category' => $exercise->category,
            'group' => $exercise->group,
            'tag' => $exercise->tag,
            'metric_type' => $exercise->metric_type,
            'difficulty' => $exercise->difficulty,
            'equipment' => $exercise->equipment_label,
            'primary' => $exercise->primary_muscles,
            'secondary' => $exercise->secondary_muscles,
            'video_url' => $exercise->video_url,
            'description' => $exercise->description,
            'source_url' => $exercise->source_url,
        ];
    }
}
