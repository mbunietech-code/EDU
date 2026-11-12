<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsEvent;
use App\Support\Analytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts one page view per full page a person opens on the website (not
 * admin pages, background fetches, prefetches, files, bots or admins), for
 * Admin -> Reports -> Analytics.
 */
class TrackPageView
{
    private const SKIP_PREFIXES = [
        'admin', 'api', 'storage', 'build', 'up', 'livewire', 'broadcasting',
        '_debugbar', 'sanctum', 'app/download', 'signed',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldTrack($request, $response)) {
                $browserId = Analytics::browserId($request);
                Analytics::record(AnalyticsEvent::PAGE_VIEW, $request, [
                    'path' => '/'.ltrim($request->path(), '/'),
                    'browser_id' => $browserId,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }
        if ($request->ajax() || $request->expectsJson() || $request->headers->has('X-Livewire')) {
            return false;
        }

        $dest = $request->headers->get('Sec-Fetch-Dest');
        if ($dest !== null && $dest !== 'document') {
            return false;
        }
        $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose', '')));
        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
            return false;
        }

        $path = trim($request->path(), '/');
        foreach (self::SKIP_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        if ($request->user()?->is_admin) {
            return false;
        }

        return ! Analytics::isBot($request->userAgent());
    }
}
