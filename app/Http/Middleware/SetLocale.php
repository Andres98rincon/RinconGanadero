<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // El selector de idioma está desactivado por ahora: la app se muestra siempre en español
        // aunque un usuario haya elegido inglés antes (no todas las vistas están traducidas).
        App::setLocale('es');

        return $next($request);
    }
}
