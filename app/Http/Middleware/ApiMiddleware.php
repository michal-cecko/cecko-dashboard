<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiAuthCredentials = config('auth.api_basic_auth');

        // Check if API basic auth is configured
        if (empty($apiAuthCredentials['login']) || empty($apiAuthCredentials['password'])) {
            return $next($request);
        }

        // Get the Authorization header
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Basic ')) {
            return response()->json([
                'error' => 'Unauthorized. #1',
            ], 403);
        }

        // Extract and decode the credentials
        $encodedCredentials = substr($authHeader, 6); // Remove "Basic " prefix
        $decodedCredentials = base64_decode($encodedCredentials);

        if (! $decodedCredentials || ! str_contains($decodedCredentials, ':')) {
            return response()->json([
                'error' => 'Unauthorized. #2',
            ], 403);
        }

        [$username, $password] = explode(':', $decodedCredentials, 2);

        // Verify credentials against config
        if ($username !== $apiAuthCredentials['login'] || $password !== $apiAuthCredentials['password']) {
            return response()->json([
                'error' => 'Unauthorized. #3',
            ], 403);
        }

        return $next($request);
    }
}
