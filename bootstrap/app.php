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
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            '2fa' => TwoFactorMiddleware::class,
            'finance.unlocked' => EnsureFinanceUnlocked::class,
        ]);

        $middleware->web(append: [UpdateLastSeen::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
