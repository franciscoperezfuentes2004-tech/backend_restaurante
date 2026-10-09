<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SessionGuard::class,
            \App\Http\Middleware\SetSucursalContext::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'can'  => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('broadcasting/*') || $request->expectsJson(),
        );

        $exceptions->reportable(function (\Throwable $e) {
            // Ignorar errores del cliente / no críticos (404, 422, 401, 403, 419, etc.)
            if (
                $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException ||
                $e instanceof \Illuminate\Validation\ValidationException ||
                $e instanceof \Illuminate\Auth\AuthenticationException ||
                $e instanceof \Illuminate\Auth\Access\AuthorizationException ||
                $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException ||
                $e instanceof \Illuminate\Session\TokenMismatchException ||
                ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() < 500)
            ) {
                return;
            }

            // Envoltura de aislamiento crítico: nunca interferir con el flujo normal de reporte ni ciclar
            try {
                $url = request()?->fullUrl() ?? 'Consola / Proceso CLI';
                app(\App\Services\NotificationService::class)->sendDeveloperErrorAlert($e, $url);
            } catch (\Throwable $handlerEx) {
                \Illuminate\Support\Facades\Log::error("Fallo al enviar alerta a desarrollador en Exception Handler: " . $handlerEx->getMessage());
            }
        });
    })->create();
