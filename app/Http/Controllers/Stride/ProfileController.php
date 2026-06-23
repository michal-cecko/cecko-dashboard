<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\StrideProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Updates the current user's Stride profile + preferences. Hard metrics live in
 * columns; soft, free-form fields (language, training style, bio, onboarding
 * flag, …) live in the `preferences` JSON bag so they survive reinstalls and are
 * readable server-side by the coach / plan generator.
 */
class ProfileController extends Controller
{
    /** Soft fields stored inside the `preferences` JSON column. */
    private const PREFERENCE_KEYS = [
        'language', 'gender', 'years_training', 'bio', 'notes', 'training_style', 'days_per_week', 'onboarded',
    ];

    /** Hard fields stored as their own columns. */
    private const COLUMN_KEYS = [
        'height_cm', 'weight_kg', 'goal_weight_kg', 'body_fat_pct', 'units', 'persona_key',
    ];

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            // columns
            'height_cm' => ['sometimes', 'integer', 'between:80,260'],
            'weight_kg' => ['sometimes', 'numeric', 'between:20,400'],
            'goal_weight_kg' => ['sometimes', 'numeric', 'between:20,400'],
            'body_fat_pct' => ['sometimes', 'numeric', 'between:1,70'],
            'units' => ['sometimes', 'in:metric,imperial'],
            'persona_key' => ['sometimes', 'in:coach,calm,nerd'],
            // preferences bag
            'language' => ['sometimes', 'in:en,sk'],
            'gender' => ['sometimes', 'in:male,female'],
            'years_training' => ['sometimes', 'numeric', 'between:0,70'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'training_style' => ['sometimes', 'array'],
            'training_style.*' => ['string', 'max:40'],
            'days_per_week' => ['sometimes', 'integer', 'between:1,7'],
            'onboarded' => ['sometimes', 'boolean'],
        ]);

        $profile = StrideProfile::firstOrCreate(['user_id' => $request->user()->id]);

        foreach (self::COLUMN_KEYS as $col) {
            if (array_key_exists($col, $data)) {
                $profile->{$col} = $data[$col];
            }
        }

        $preferences = $profile->preferences ?? [];
        foreach (self::PREFERENCE_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $preferences[$key] = $data[$key];
            }
        }
        $profile->preferences = $preferences;
        $profile->save();

        return response()->json(['profile' => $this->payload($profile->fresh())]);
    }

    private function payload(StrideProfile $profile): array
    {
        $prefs = $profile->preferences ?? [];

        return [
            'height_cm' => $profile->height_cm,
            'weight_kg' => $profile->weight_kg,
            'goal_weight_kg' => $profile->goal_weight_kg,
            'body_fat_pct' => $profile->body_fat_pct,
            'units' => $profile->units,
            'persona_key' => $profile->persona_key,
            'language' => $prefs['language'] ?? 'en',
            'gender' => $prefs['gender'] ?? null,
            'years_training' => $prefs['years_training'] ?? null,
            'bio' => $prefs['bio'] ?? null,
            'notes' => $prefs['notes'] ?? null,
            'training_style' => $prefs['training_style'] ?? [],
            'days_per_week' => $prefs['days_per_week'] ?? null,
            'onboarded' => (bool) ($prefs['onboarded'] ?? false),
        ];
    }
}
