<?php

namespace App\Http\Controllers\Garaz;

use App\Http\Controllers\Controller;
use App\Models\Common\UserApiToken;
use App\Models\Garaz\KnowledgeNote;
use App\Models\Garaz\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeNoteApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $this->authenticate($request);

        if ($token === null || ! $token->hasAbility('knowledge:write')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'vehicle_id' => ['nullable', 'integer'],
        ]);

        if (! empty($data['vehicle_id'])) {
            $vehicleOwned = Vehicle::query()
                ->where('id', $data['vehicle_id'])
                ->where('user_id', $token->user_id)
                ->exists();

            if (! $vehicleOwned) {
                return response()->json(['error' => 'Vehicle not found'], 404);
            }
        }

        $note = KnowledgeNote::create([
            'user_id' => $token->user_id,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'source' => 'bookmarklet',
            'tags' => $data['tags'] ?? null,
            'captured_at' => now(),
        ]);

        $token->update(['last_used_at' => now()]);

        return response()->json([
            'id' => $note->id,
            'message' => 'Saved',
        ], 201);
    }

    private function authenticate(Request $request): ?UserApiToken
    {
        $bearer = $request->bearerToken();

        if ($bearer === null) {
            return null;
        }

        $token = UserApiToken::active()->byRawToken($bearer)->first();

        return $token;
    }
}
