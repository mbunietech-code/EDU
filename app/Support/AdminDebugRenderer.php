<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders a detailed error page — but only for signed-in admins, and only when
 * the "show error details" switch is on (Settings -> Developer tools).
 */
class AdminDebugRenderer
{
    private const REDACT = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        '_token', 'token', 'api_token', 'secret', 'pin', 'cvv', 'card', 'card_number',
    ];

    public static function render(Throwable $e, Request $request): ?Response
    {
        // Let the framework handle its own already-formatted responses / normal flow.
        if ($e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof ModelNotFoundException
            || $e instanceof TokenMismatchException) {
            return null;
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return null;
        }

        if (config('app.debug')) {
            return null; // local: let Ignition / Symfony render
        }

        if (! DevSettings::adminDebugEnabled()) {
            return null;
        }

        $user = $request->user();
        if (! $user || ! $user->is_admin) {
            return null;
        }

        // JSON clients get JSON.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(30)->map(fn ($f) => trim(
                    ($f['file'] ?? '[internal]').':'.($f['line'] ?? '?').'  '.($f['class'] ?? '').($f['type'] ?? '').($f['function'] ?? '')
                ))->all(),
            ], 500);
        }

        return response()->view('errors.admin-debug', [
            'e' => $e,
            'snippet' => self::snippet($e->getFile(), $e->getLine()),
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => optional($request->route())->getName(),
                'input' => self::redact($request->except(self::REDACT)),
            ],
        ], 500);
    }

    /**
     * @return list<array{n:int, code:string, current:bool}>
     */
    private static function snippet(string $file, int $line, int $pad = 12): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $start = max(0, $line - 1 - $pad);
        $end = min(count($lines) - 1, $line - 1 + $pad);

        $out = [];
        for ($i = $start; $i <= $end; $i++) {
            $out[] = [
                'n' => $i + 1,
                'code' => $lines[$i],
                'current' => ($i + 1) === $line,
            ];
        }

        return $out;
    }

    private static function redact(array $input): array
    {
        array_walk_recursive($input, function (&$v) {
            if (is_string($v) && strlen($v) > 400) {
                $v = substr($v, 0, 400).'…';
            }
        });

        return $input;
    }
}
