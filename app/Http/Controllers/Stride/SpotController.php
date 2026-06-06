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
