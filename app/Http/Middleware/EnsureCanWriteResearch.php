<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanWriteResearch
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user() && $request->user()->canWriteResearch(),
            403,
            'You have not been granted access to publish research yet.',
        );

        return $next($request);
    }
}
