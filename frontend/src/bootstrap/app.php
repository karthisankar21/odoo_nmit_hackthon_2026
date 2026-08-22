<?php
// =============================================================================
// bootstrap/app.php
// -----------------------------------------------------------------------------
// Laravel 11 application bootstrap.
// Registers custom middleware aliases used in routes/web.php:
//   auth.custom  → AuthMiddleware  (checks JWT token in session)
//   role         → RoleMiddleware  (checks user role in session)
// =============================================================================

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ── Register custom middleware aliases ────────────────────────────────
        // 'auth.custom' → checks session('token') exists
        // 'role'        → checks session('role') matches required role
        $middleware->alias([
            'auth.custom' => \App\Http\Middleware\AuthMiddleware::class,
            'role'        => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
