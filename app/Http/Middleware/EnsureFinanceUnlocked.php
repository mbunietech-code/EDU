<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('finance_unlocked')) {
            return redirect()->route('admin.finance.pin');
        }

        return $next($request);
    }
}
