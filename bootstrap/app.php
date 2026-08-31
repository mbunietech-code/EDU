<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureFinanceUnlocked;
use App\Http\Middleware\TwoFactorMiddleware;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            '2fa' => TwoFactorMiddleware::class,
            'finance.unlocked' => EnsureFinanceUnlocked::class,
            'research.contribute' => \App\Http\Middleware\EnsureCanWriteResearch::class,
        ]);

        $middleware->web(append: [UpdateLastSeen::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            \App\Support\ErrorLogger::handle($e);
        });

        // Signed-in admins can opt into a detailed error page (Settings ->
        // Developer tools). Visitors are never affected.
        $exceptions->render(function (\Throwable $e, $request) {
            return \App\Support\AdminDebugRenderer::render($e, $request);
        });
    })->create();
