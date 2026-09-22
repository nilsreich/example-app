<?php

use App\Providers\AiServiceProvider;
use App\Providers\FeedbackServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/entra.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        AiServiceProvider::class,
        FeedbackServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Hinter Caddy (compose.prod.yaml) laufen alle Requests über einen
        // Reverse-Proxy → X-Forwarded-* vertrauen, damit generated URLs
        // (z. B. ROI-Deeplinks) korrekt HTTPS verwenden.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
