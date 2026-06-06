<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Common\User;
use App\Models\Common\UserApiToken;
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
            ],
        ];
    }
}
