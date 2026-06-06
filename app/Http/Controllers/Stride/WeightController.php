<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\StrideProfile;
use App\Models\Stride\WeightEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);

        $entries = WeightEntry::ownedBy($user)
            ->orderBy('recorded_on')
            ->get()
            ->map(fn (WeightEntry $e) => [
                'date' => $e->recorded_on->toDateString(),
                'kg' => $e->kg,
                'note' => $e->note,
            ]);

        return response()->json([
            'entries' => $entries->values(),
            'goal_weight_kg' => $profile->goal_weight_kg,
            'current_kg' => $entries->last()['kg'] ?? $profile->weight_kg,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'kg' => ['required', 'numeric', 'between:20,400'],
            'recorded_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $recordedOn = $data['recorded_on'] ?? now()->toDateString();

        // One entry per day — upsert so re-logging today overwrites.
        $entry = WeightEntry::updateOrCreate(
            ['user_id' => $user->id, 'recorded_on' => $recordedOn],
            ['kg' => $data['kg'], 'note' => $data['note'] ?? null],
        );

        // Keep the profile's current weight in sync with the latest entry.
        StrideProfile::firstOrCreate(['user_id' => $user->id])
            ->update(['weight_kg' => WeightEntry::ownedBy($user)->orderByDesc('recorded_on')->value('kg')]);

        return response()->json([
            'entry' => [
                'date' => $entry->recorded_on->toDateString(),
                'kg' => $entry->kg,
                'note' => $entry->note,
            ],
        ], 201);
    }
}
