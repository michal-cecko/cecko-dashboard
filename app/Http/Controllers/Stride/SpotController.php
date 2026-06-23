<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\Spot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Training spots. Returns the user's own spots plus the curated/official
 * directory, in the shape the prototype's Spots screen consumes.
 */
class SpotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $mine = Spot::ownedBy($user)->orderBy('id')->get();
        $official = Spot::official()->orderBy('name')->get();

        return response()->json([
            'spots' => $mine->map(fn (Spot $s, int $i) => $this->payload($s, $i === 0))->values(),
            'official' => $official->map(fn (Spot $s) => $this->payload($s, false))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:40'],
            'size' => ['nullable', 'string', 'max:40'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:60'],
        ]);

        $spot = Spot::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'size' => $data['size'] ?? null,
            'blurb' => $data['prompt'] ?? null,
            'equipment' => $data['equipment'] ?? [],
            'notes' => $data['notes'] ?? null,
            'is_official' => false,
            'is_verified' => false,
        ]);

        // index() treats the lowest-id owned spot as the default.
        $isDefault = Spot::ownedBy($request->user())->min('id') === $spot->id;

        return response()->json(['spot' => $this->payload($spot, $isDefault)], 201);
    }

    private function payload(Spot $spot, bool $isDefault): array
    {
        return [
            'id' => 'spot-'.$spot->id,
            'name' => $spot->name,
            'type' => $spot->type,
            'size' => $spot->size,
            'isDefault' => $isDefault,
            'isOfficial' => $spot->is_official,
            'verified' => $spot->is_verified,
            'equipment' => $spot->equipment ?? [],
            'prompt' => $spot->blurb,
            'schedule' => ['type' => 'default'],
            'notes' => $spot->notes,
        ];
    }
}
