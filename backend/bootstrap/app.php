<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php', // Aquí es donde se registran tus rutas API
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Registrar middleware personalizado de roles y seguridad
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'rate.limit' => \App\Http\Middleware\RateLimitingMiddleware::class,
            'security.audit' => \App\Http\Middleware\SecurityAuditMiddleware::class,
            'request.logging' => \App\Http\Middleware\RequestLoggingMiddleware::class,
            'msal.headers' => \App\Http\Middleware\MsalHeaders::class,
        ]);

        // Middleware global para seguridad
        $middleware->web(append: [
            \App\Http\Middleware\AddCoopHeader::class,
            \App\Http\Middleware\RequestLoggingMiddleware::class,
            \App\Http\Middleware\SecurityAuditMiddleware::class,
        ]);
        
        // Middleware para API con CORS personalizado (primero), logging, MSAL y seguridad
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
            \App\Http\Middleware\MsalHeaders::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\AddCoopHeader::class,
            \App\Http\Middleware\RequestLoggingMiddleware::class, // Re-enabled for security audit
            \App\Http\Middleware\SecurityAuditMiddleware::class, // Re-enabled for security audit
        ]);
    })
        ->withExceptions(function (Exceptions $exceptions) {
            $exceptions->render(function (AuthenticationException $exception, $request) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'No autenticado.',
                    ], 401);
                }

                return redirect()->guest('/');
            });
        })->create();
