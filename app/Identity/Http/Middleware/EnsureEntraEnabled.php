<?php

namespace App\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt die Entra-SSO-Routen, wenn SSO nicht aktiviert ist (ENTRA_ENABLED=false).
 *
 * Bewusst als Middleware statt als Route-Registrierungs-Guard: so wird der
 * Schalter pro Request ausgewertet und ist in Tests steuerbar.
 */
final class EnsureEntraEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('entra.enabled'), 404);

        return $next($request);
    }
}
