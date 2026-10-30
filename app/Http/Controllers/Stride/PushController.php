<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Stride\PushSubscription;
use App\Models\Stride\StrideProfile;
use App\Services\Stride\Push\PushEndpoint;
use App\Services\Stride\Push\PushTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PushController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['sometimes', 'string', 'max:2048', 'url'],
        ]);
        $subscription = null;

        if (isset($data['endpoint'])) {
            $subscription = PushSubscription::query()
                ->where('user_id', $request->user()->id)
                ->where('token_id', $request->attributes->get('stride_token')->id)
                ->where('endpoint_hash', hash('sha256', $data['endpoint']))
                ->exists();
        }

        return response()->json([
            'configured' => $this->configured(),
            'public_key' => $this->configured() ? config('stride-push.public_key') : null,
            'subscribed' => (bool) $subscription,
            'preferences' => $this->currentPreferences($request->user()->id),
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'workout_reminder' => ['sometimes', 'boolean'],
            'workout_reminder_time' => ['sometimes', 'date_format:H:i'],
            'weigh_reminder' => ['sometimes', 'boolean'],
            'weigh_reminder_time' => ['sometimes', 'date_format:H:i'],
            'coach_pokes' => ['sometimes', 'boolean'],
            'notification_timezone' => ['sometimes', 'timezone'],
        ]);

        $profile = StrideProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $stored = $profile->preferences ?? [];
        $stored = array_merge($stored, $data);
        $profile->update(['preferences' => $stored]);

        return response()->json(['preferences' => $this->preferencePayload($stored)]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription.endpoint' => ['required', 'string', 'max:2048', 'url'],
            'subscription.expirationTime' => ['nullable', 'integer', 'min:1'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'subscription.keys.auth' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $subscription = $data['subscription'];
        $publicKey = $this->decodeKey($subscription['keys']['p256dh'], 65, 'subscription.keys.p256dh');
        if (! str_starts_with($publicKey, "\x04")) {
            throw ValidationException::withMessages(['subscription.keys.p256dh' => ['The public key is invalid.']]);
        }
        $this->decodeKey($subscription['keys']['auth'], 16, 'subscription.keys.auth');

        $endpointHash = PushEndpoint::validate($subscription['endpoint']);
        $existing = PushSubscription::query()->where('endpoint_hash', $endpointHash)->first();

        if ($existing !== null && $existing->user_id !== $request->user()->id) {
            throw ValidationException::withMessages(['subscription.endpoint' => ['This endpoint is already registered.']]);
        }

        if ($existing !== null && ($existing->public_key !== $subscription['keys']['p256dh']
            || $existing->auth_token !== $subscription['keys']['auth'])) {
            throw ValidationException::withMessages(['subscription.endpoint' => ['The endpoint keys do not match the existing subscription.']]);
        }

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'user_id' => $request->user()->id,
                'token_id' => $request->attributes->get('stride_token')->id,
                'endpoint' => $subscription['endpoint'],
                'public_key' => $subscription['keys']['p256dh'],
                'auth_token' => $subscription['keys']['auth'],
            ],
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048', 'url']]);
        $endpointHash = hash('sha256', $data['endpoint']);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('token_id', $request->attributes->get('stride_token')->id)
            ->where('endpoint_hash', $endpointHash)
            ->delete();

        return response()->json(['unsubscribed' => true]);
    }

    public function test(Request $request, PushTransport $transport): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048', 'url']]);
        $user = $request->user();
        $subscription = PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('token_id', $request->attributes->get('stride_token')->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->first();

        abort_if($subscription === null, 404, 'Subscription not found.');
        abort_unless($this->configured(), 503, 'Push notifications are not configured.');

        $key = 'stride-push-test:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($key, 1), 429, 'Please wait before sending another test notification.');
        RateLimiter::hit($key, 60);

        $result = $transport->send($subscription, [
            'title' => 'Stride test notification',
            'body' => 'Push notifications are working.',
            'tag' => 'stride-test',
            'url' => '/?screen=home',
        ]);

        if ($result->status === 'expired') {
            $subscription->delete();
        }

        abort_unless($result->status === 'sent', 502, 'The push service could not deliver the test notification.');

        return response()->json(['sent' => true]);
    }

    private function currentPreferences(int $userId): array
    {
        $profile = StrideProfile::firstOrCreate(['user_id' => $userId]);

        return $this->preferencePayload($profile->preferences ?? []);
    }

    private function preferencePayload(array $stored): array
    {
        return [
            'workout_reminder' => (bool) ($stored['workout_reminder'] ?? false),
            'workout_reminder_time' => $stored['workout_reminder_time'] ?? '18:00',
            'weigh_reminder' => (bool) ($stored['weigh_reminder'] ?? false),
            'weigh_reminder_time' => $stored['weigh_reminder_time'] ?? '06:00',
            'coach_pokes' => (bool) ($stored['coach_pokes'] ?? false),
            'notification_timezone' => $stored['notification_timezone'] ?? 'Europe/Prague',
        ];
    }

    private function configured(): bool
    {
        return filled(config('stride-push.public_key'))
            && filled(config('stride-push.private_key'))
            && filled(config('stride-push.subject'));
    }

    private function decodeKey(string $key, int $expectedLength, string $field): string
    {
        $decoded = base64_decode(strtr($key, '-_', '+/').str_repeat('=', (4 - strlen($key) % 4) % 4), true);

        if (! is_string($decoded) || strlen($decoded) !== $expectedLength) {
            throw ValidationException::withMessages([$field => ['The push key has an invalid length.']]);
        }

        return $decoded;
    }
}
