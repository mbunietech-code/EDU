<?php

namespace App\Support;

use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\Admin\ErrorOccurred;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Records real server-side exceptions into the `error_logs` table so an admin
 * can see what broke (and where) from Admin -> Error Logs, and notifies admins
 * the first time a new fault appears.
 */
class ErrorLogger
{
    private static bool $inside = false;

    /** Exceptions that are normal control-flow, not bugs. */
    private const IGNORE = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
    ];

    private const REDACT = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        '_token', 'token', 'api_token', 'secret', 'pin', 'cvv', 'card', 'card_number',
    ];

    public static function handle(Throwable $e): void
    {
        if (self::$inside) {
            return;
        }
        self::$inside = true;

        try {
            if (self::shouldIgnore($e) || ! Schema::hasTable('error_logs')) {
                return;
            }

            $fingerprint = hash('sha256', implode('|', [
                get_class($e),
                $e->getFile(),
                $e->getLine(),
            ]));

            $request = request();
            $now = Carbon::now();

            $existing = ErrorLog::where('fingerprint', $fingerprint)->first();

            $payload = [
                'level' => 'error',
                'exception' => get_class($e),
                'message' => \Illuminate\Support\Str::limit($e->getMessage(), 2000),
                'file' => self::relativePath($e->getFile()),
                'line' => $e->getLine(),
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
                'user_id' => auth()->id(),
                'ip' => $request?->ip(),
                'trace' => \Illuminate\Support\Str::limit($e->getTraceAsString(), 20000),
                'context' => self::context($request),
                'last_seen_at' => $now,
            ];

            if ($existing) {
                $existing->fill($payload);
                $existing->occurrences = $existing->occurrences + 1;
                $existing->resolved_at = null;   // reopen on recurrence
                $existing->resolved_by = null;
                $existing->save();

                return;
            }

            $log = ErrorLog::create($payload + [
                'fingerprint' => $fingerprint,
                'occurrences' => 1,
                'first_seen_at' => $now,
            ]);

            self::notifyAdmins($log);
        } catch (Throwable $ignored) {
            // Never let error logging break the response.
        } finally {
            self::$inside = false;
        }
    }

    private static function shouldIgnore(Throwable $e): bool
    {
        foreach (self::IGNORE as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        // HTTP exceptions below 500 (404, 403, 419, 429, ...) are not bugs.
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return true;
        }

        return false;
    }

    private static function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private static function context($request): array
    {
        if (! $request) {
            return [];
        }

        $input = $request->except(self::REDACT);
        array_walk_recursive($input, function (&$v) {
            if (is_string($v) && strlen($v) > 500) {
                $v = substr($v, 0, 500).'…';
            }
        });

        return [
            'route' => optional($request->route())->getName(),
            'input' => $input,
            'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 255),
        ];
    }

    private static function notifyAdmins(ErrorLog $log): void
    {
        try {
            $admins = User::where('is_admin', true)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ErrorOccurred($log));
            }
        } catch (Throwable $ignored) {
            //
        }
    }
}
