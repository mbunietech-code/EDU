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
            'vault.unlocked' => \App\Http\Middleware\EnsureVaultUnlocked::class,
            'research.contribute' => \App\Http\Middleware\EnsureCanWriteResearch::class,
        ]);

        // Already signed in and opening the sign-in page (e.g. in a new tab):
        // admins go to the admin panel, everyone else to their dashboard.
        $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $request) => $request->user()?->is_admin
            ? route('admin.dashboard')
            : route('dashboard'));

        // RestrictGuests: meeting guests (guest links) may only use their one room.
        // TrackPageView: page views + unique devices for Reports -> Analytics.
        $middleware->web(append: [UpdateLastSeen::class, \App\Http\Middleware\RestrictGuests::class, \App\Http\Middleware\TrackPageView::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            \App\Support\ErrorLogger::handle($e);
        });

        // "Page expired" (419) while pressing Renew on an order: no error page,
        // back on the order with a message asking for a new order instead.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419 && $request->isMethod('POST')
                && preg_match('#^my-orders/(\d+)/renew$#', $request->path(), $m)) {
                return \App\Http\Controllers\User\OrderController::renewFailed($m[1], 'Your page expired before Renew was pressed.');
            }

            return null;
        });

        // Signed-in admins can opt into a detailed error page (Settings ->
        // Developer tools). Visitors are never affected.
        $exceptions->render(function (\Throwable $e, $request) {
            return \App\Support\AdminDebugRenderer::render($e, $request);
        });
    })->create();
