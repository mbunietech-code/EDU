<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every web request: a signed-in user whose account was suspended (or made
 * inactive) is signed out at once, not only at the next sign-in. The MHub app
 * gets the same rule from Sanctum (AppServiceProvider: suspended users' tokens
 * stop working).
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'This account is not active. Contact support.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => self::MESSAGE], 403)
                : redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
