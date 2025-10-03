<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Trust all proxies in production for HTTPS
        $middleware->trustProxies(at: '*');

        // Sanctum abilities middleware
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
            'track.api' => \App\Http\Middleware\TrackApiUsage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON responses for API routes
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*');
        });
    })->create();
