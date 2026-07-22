<?php

namespace App\Http\Middleware\Stride;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Common\UserApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates Stride mobile requests with a Bearer UserApiToken.
 *
 * Reuses the existing app-wide token system (App\Models\Common\UserApiToken)
 * rather than introducing Sanctum. A valid, active token carrying the "stride"
 * (or "*") ability resolves the request user so controllers can call
 * $request->user(); the token itself is available via $request->attributes.
 */
class AuthenticateStrideToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null) {
            return $this->unauthorized();
        }

        $token = UserApiToken::query()->active()->byRawToken($bearer)->first();

        if ($token === null || ! $token->hasAbility('stride')) {
            return $this->unauthorized();
        }

        // Panel accounts double as app accounts, gated by the STRIDE_USER
        // capability — revoking it locks out existing tokens immediately.
        if ($token->user === null || ! $token->user->hasCapability(UserCapabilityEnum::STRIDE_USER)) {
            return response()->json(['error' => 'Stride access is not enabled for this account.'], 403);
        }

        // Resolve the authenticated user for the rest of the request.
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('stride_token', $token);

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
