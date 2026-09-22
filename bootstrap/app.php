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
        // (z. B. ROI-Deeplinks) korrekt HTTPS verwenden. DIREKT erreichbare
        // Instanzen (dev serve, offen exponiert) dürfen KEINE fremden
        // X-Forwarded-Header akzeptieren → nur explizit genannte Proxy-CIDRs:
        // TRUSTED_PROXIES='10.0.0.0/8' o. ä. in Produktion; Default = localhost.
        // env() ist hier nötig: config() ist beim Bootstrap der Middleware
        // noch nicht verfügbar (Config-Repository wird später geladen).
        $trustedProxies = env('TRUSTED_PROXIES', '127.0.0.1'); // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig

        $middleware->trustProxies(
            at: array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $trustedProxies),
            ))),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
