<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Common\AiConnection;
use App\Services\Common\Ai\AiCatalog;
use App\Services\Common\Ai\AiCredentials;
use App\Services\Common\Ai\AiProviderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-user "connect your own AI model" endpoints. The user picks a provider +
 * model in the app wizard; we validate their API key with one tiny call and
 * store it (encrypted). Anyone who hasn't connected runs on the free tier.
 *
 * API-key auth only: Anthropic banned subscription-OAuth tokens for third-party
 * apps (Feb 2026), so BYOK here means a Console/API key billed to the user.
 */
class AiConnectionController extends Controller
{
    public function __construct(
        private readonly AiProviderResolver $resolver,
    ) {}

    /** Current connection (safe fields) + the provider/model catalog. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['ai' => AiCatalog::forUser($request->user())]);
    }

    /** Save + validate a connection. Fires a cheap ping against the chosen model. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:anthropic,gemini,openai,free'],
            'auth_type' => ['required', 'in:api_key,none'],
            'api_key' => ['nullable', 'string', 'max:400'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $provider = $data['provider'];

        // Free tier — no credentials, always usable.
        if ($provider === 'free') {
            AiConnection::updateOrCreate(['user_id' => $user->id], [
                'provider' => 'free',
                'auth_type' => 'none',
                'credentials' => null,
                'model' => null,
                'status' => 'active',
                'last_verified_at' => now(),
                'last_error' => null,
            ]);

            return response()->json(['ok' => true, 'error' => null, 'ai' => AiCatalog::forUser($user)]);
        }

        $apiKey = trim((string) ($data['api_key'] ?? ''));
        if ($apiKey === '') {
            return response()->json(['error' => 'An API key is required.'], 422);
        }

        $model = $data['model'] ?? null;
        if ($model !== null && ! AiCatalog::isValidModel($provider, $model)) {
            return response()->json(['error' => 'Unknown model for this provider.'], 422);
        }

        $probeModel = $model ?: (string) config("stride.ai.providers.{$provider}.default");
        $result = $this->resolver->probe($provider, new AiCredentials(apiKey: $apiKey), $probeModel);

        AiConnection::updateOrCreate(['user_id' => $user->id], [
            'provider' => $provider,
            'auth_type' => 'api_key',
            'credentials' => ['api_key' => $apiKey],
            'model' => $model,
            'status' => $result['ok'] ? 'active' : 'invalid',
            'last_verified_at' => $result['ok'] ? now() : null,
            'last_error' => $result['ok'] ? null : $result['error'],
        ]);

        return response()->json([
            'ok' => $result['ok'],
            'error' => $result['error'],
            'ai' => AiCatalog::forUser($user),
        ], $result['ok'] ? 200 : 422);
    }

    /** Re-validate the stored connection. */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $connection = AiConnection::firstWhere('user_id', $user->id);

        if ($connection === null || $connection->provider === 'free') {
            return response()->json(['ok' => true, 'error' => null, 'ai' => AiCatalog::forUser($user)]);
        }

        $model = $connection->model ?: (string) config("stride.ai.providers.{$connection->provider}.default");
        $result = $this->resolver->probe($connection->provider, $connection->toCredentials(), $model);

        $connection->update([
            'status' => $result['ok'] ? 'active' : 'invalid',
            'last_verified_at' => $result['ok'] ? now() : $connection->last_verified_at,
            'last_error' => $result['ok'] ? null : $result['error'],
        ]);

        return response()->json(['ok' => $result['ok'], 'error' => $result['error'], 'ai' => AiCatalog::forUser($user)]);
    }

    /** Change the connection's default model. */
    public function updateModel(Request $request): JsonResponse
    {
        $data = $request->validate(['model' => ['nullable', 'string', 'max:100']]);
        $user = $request->user();
        $connection = AiConnection::firstWhere('user_id', $user->id);

        if ($connection === null || $connection->provider === 'free') {
            return response()->json(['error' => 'Connect a provider first.'], 422);
        }

        if ($data['model'] !== null && ! AiCatalog::isValidModel($connection->provider, $data['model'])) {
            return response()->json(['error' => 'Unknown model for this provider.'], 422);
        }

        $connection->update(['model' => $data['model']]);

        return response()->json(['ok' => true, 'ai' => AiCatalog::forUser($user)]);
    }

    /** Disconnect — the user drops back to the free tier. */
    public function destroy(Request $request): JsonResponse
    {
        AiConnection::where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true, 'ai' => AiCatalog::forUser($request->user())]);
    }
}
