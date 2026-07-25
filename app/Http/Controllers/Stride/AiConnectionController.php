<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Models\Common\AiConnection;
use App\Services\Common\Ai\AiCatalog;
use App\Services\Common\Ai\AiCredentials;
use App\Services\Common\Ai\AiProviderResolver;
use App\Services\Common\Ai\Auth\AnthropicOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Per-user "connect your own AI model" endpoints. The user picks a provider +
 * model in the app wizard; we validate their key with one tiny call and store
 * it (encrypted). Anyone who hasn't connected runs on the free tier.
 */
class AiConnectionController extends Controller
{
    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly AnthropicOAuthService $oauth,
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
            'auth_type' => ['required', 'in:api_key,oauth,none'],
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

        // Subscription login goes through the OAuth flow, not here.
        if ($data['auth_type'] === 'oauth') {
            return response()->json(['error' => 'Use the subscription login flow to connect via OAuth.'], 422);
        }

        if (! AiCatalog::supportsAuthType($provider, 'api_key')) {
            return response()->json(['error' => 'This provider does not support API-key auth.'], 422);
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

        if ($connection->auth_type === 'oauth') {
            try {
                $this->oauth->ensureFresh($connection);
                $ok = true;
                $error = null;
            } catch (Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
            }
        } else {
            $model = $connection->model ?: (string) config("stride.ai.providers.{$connection->provider}.default");
            $result = $this->resolver->probe($connection->provider, $connection->toCredentials(), $model);
            $ok = $result['ok'];
            $error = $result['error'];
        }

        $connection->update([
            'status' => $ok ? 'active' : 'invalid',
            'last_verified_at' => $ok ? now() : $connection->last_verified_at,
            'last_error' => $ok ? null : $error,
        ]);

        return response()->json(['ok' => $ok, 'error' => $error, 'ai' => AiCatalog::forUser($user)]);
    }

    /** Change the connection's default model (e.g. after subscription login). */
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

    /** Begin the Anthropic subscription (OAuth) flow — returns the authorize URL. */
    public function authorizeAnthropic(Request $request): JsonResponse
    {
        if (! $this->oauth->isEnabled()) {
            return response()->json(['error' => 'Subscription login is not enabled.'], 404);
        }

        $out = $this->oauth->beginAuthorization($request->user());

        return response()->json(['url' => $out['url'], 'state' => $out['state']]);
    }

    /**
     * OAuth redirect target (public — the browser hits this, not the app). The
     * user is identified from the PKCE state we stashed, so no Bearer token is
     * needed. Renders a tiny "return to the app" page.
     */
    public function callbackAnthropic(Request $request): Response
    {
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        try {
            if ($code === '' || $state === '') {
                throw new \RuntimeException('Missing code or state.');
            }
            $this->oauth->handleCallback($code, $state);
            $message = 'Connected. You can return to the app.';
            $ok = true;
        } catch (Throwable $e) {
            report($e);
            $message = 'Could not complete sign-in: '.$e->getMessage();
            $ok = false;
        }

        $color = $ok ? '#16a34a' : '#dc2626';
        $html = '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<div style="font:16px -apple-system,system-ui,sans-serif;max-width:28rem;margin:20vh auto;padding:2rem;text-align:center">'
            ."<div style=\"font-size:2.5rem;color:{$color}\">".($ok ? '&#10003;' : '&#10007;').'</div>'
            ."<p>{$message}</p>"
            .'<p style="color:#888">You can close this window.</p></div>';

        return response($html, $ok ? 200 : 400)->header('Content-Type', 'text/html');
    }
}
