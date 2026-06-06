<?php

use App\Http\Middleware\ApiMiddleware;
use App\Http\Middleware\NoIndexHeaders;
use App\Http\Middleware\Stride\AuthenticateStrideToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Stride mobile API. Uses Bearer token auth (not the Basic-auth
            // ApiMiddleware on the global api group), so it gets its own stack.
            // SubstituteBindings is required for implicit route-model binding.
            Route::middleware(['throttle:60,1', SubstituteBindings::class])
                ->prefix('api/stride')
                ->group(base_path('routes/stride.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            ApiMiddleware::class,
        ]);
        $middleware->alias([
            'stride.auth' => AuthenticateStrideToken::class,
        ]);
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            NoIndexHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
