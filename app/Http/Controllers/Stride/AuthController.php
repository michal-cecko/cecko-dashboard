<?php

namespace App\Http\Controllers\Stride;

use App\Enums\Common\UserCapabilityEnum;
use App\Http\Controllers\Controller;
use App\Models\Common\User;
use App\Models\Common\UserApiToken;
use App\Models\Stride\PersonalRecord;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Stride mobile auth. Exchanges email/password for a Bearer UserApiToken
 * scoped to the "stride" ability. The raw token is returned exactly once.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->hasCapability(UserCapabilityEnum::STRIDE_USER)) {
            return response()->json([
                'error' => 'Stride access is not enabled for this account.',
            ], 403);
        }

        $raw = UserApiToken::generateRaw();

        UserApiToken::create([
            'user_id' => $user->id,
            'name' => $data['device_name'] ?? 'Stride app',
            'token' => hash('sha256', $raw),
            'abilities' => ['stride'],
        ]);

        return response()->json([
            'token' => $raw,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('stride_token');

        if ($token instanceof UserApiToken) {
            $token->forceFill(['revoked_at' => now()])->save();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    private function userPayload(User $user): array
    {
        $profile = StrideProfile::query()->firstOrCreate(['user_id' => $user->id]);

        $done = Session::query()->where('user_id', $user->id)->where('status', 'done');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'joined' => $user->created_at?->format('M Y'),
            'onboarded' => (bool) ($profile->preferences['onboarded'] ?? false),
            'total_sessions' => (clone $done)->count(),
            'total_hours' => (int) round((clone $done)->sum('duration_min') / 60),
            'profile' => [
                'height_cm' => $profile->height_cm,
                'weight_kg' => $profile->weight_kg,
                'goal_weight_kg' => $profile->goal_weight_kg,
                'body_fat_pct' => $profile->body_fat_pct,
                'persona_key' => $profile->persona_key,
                'units' => $profile->units,
                'streak_days' => $profile->streak_days,
                'language' => $profile->preferences['language'] ?? 'en',
                'gender' => $profile->preferences['gender'] ?? null,
                'birth_year' => $profile->preferences['birth_year'] ?? null,
                // Setup / training preferences — so the app can show & edit them
                // after onboarding (previously stored but never returned).
                'years_training' => $profile->preferences['years_training'] ?? null,
                'training_style' => $profile->preferences['training_style'] ?? [],
                'days_per_week' => $profile->preferences['days_per_week'] ?? null,
                'bio' => $profile->preferences['bio'] ?? null,
                'notes' => $profile->preferences['notes'] ?? null,
                'age' => ! empty($profile->preferences['birth_year'])
                    ? max(0, now()->year - (int) $profile->preferences['birth_year'])
                    : null,
                'personal_records' => PersonalRecord::ownedBy($user)
                    ->orderByDesc('achieved_on')->orderByDesc('id')->get()
                    ->map(fn (PersonalRecord $pr) => [
                        'id' => $pr->id,
                        'exercise_id' => $pr->exercise_id,
                        'label' => $pr->label,
                        'metric_type' => $pr->metric_type,
                        'metrics' => $pr->metrics ?? [],
                        'display' => $pr->display(),
                        'achieved_on' => $pr->achieved_on?->toDateString(),
                        'form_quality' => $pr->form_quality,
                    ])->values(),
            ],
        ];
    }
}
