<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\SetLocale;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if (
                $request->expectsJson()
                || $request->is('api/*')
                || $exception instanceof AuthenticationException
                || $exception instanceof ValidationException
            ) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            return response()->view('errors.general', [
                'status' => $status,
                'message' => match ($status) {
                    403 => 'No tienes permiso para realizar esta acción.',
                    404 => 'No encontramos el registro o la página que buscas.',
                    419 => 'La sesión expiró. Recarga la página e inténtalo nuevamente.',
                    429 => 'Se recibieron demasiadas solicitudes. Espera un momento e inténtalo de nuevo.',
                    default => 'No pudimos completar la operación. Inténtalo nuevamente.',
                },
            ], $status);
        });
    })->create();
