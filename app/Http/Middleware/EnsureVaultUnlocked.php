<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The password vault stays open for IDLE_MINUTES after the last action, then
 * asks for the PIN again (so a forgotten open tab does not leak passwords).
 */
class EnsureVaultUnlocked
{
    public const IDLE_MINUTES = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $at = (int) $request->session()->get('vault_unlocked_at', 0);

        if ($at === 0 || now()->timestamp - $at > self::IDLE_MINUTES * 60) {
            $request->session()->forget('vault_unlocked_at');

            return $request->expectsJson()
                ? response()->json(['message' => 'The vault is locked. Enter the PIN again.'], 423)
                : redirect()->route('admin.vault.pin');
        }

        $request->session()->put('vault_unlocked_at', now()->timestamp);

        return $next($request);
    }
}
