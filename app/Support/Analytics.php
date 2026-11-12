<?php

namespace App\Support;

use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records page views, app downloads and app opens for Admin -> Reports ->
 * Analytics. Never throws: analytics must not break a page or a download.
 *
 * A device is recognised by, in order: the app's own device id, the
 * long-lived "mh_vid" browser cookie, or (no cookie, e.g. a direct download
 * link or an older app) its IP address + user agent. Whatever is used is
 * hashed with APP_KEY before it is stored, so raw cookies and IPs are never
 * kept.
 */
class Analytics
{
    public const COOKIE = 'mh_vid';

    private const COOKIE_MINUTES = 60 * 24 * 365 * 2;

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|archiver|facebookexternalhit|whatsapp|telegram|preview|curl|wget|python|httpclient|okhttp|java\/|go-http|headless|phantom|lighthouse|pagespeed|monitor|uptime|pingdom|scan|scrap|fetch|feed|libwww|axios|node-fetch|postman/i';

    /**
     * The browser's device id from the cookie, creating (and queueing) one on
     * the first visit.
     */
    public static function browserId(Request $request): string
    {
        $id = (string) $request->cookie(self::COOKIE, '');
        if (! preg_match('/^[A-Za-z0-9]{20,64}$/', $id)) {
            $id = Str::random(32);
            Cookie::queue(Cookie::make(self::COOKIE, $id, self::COOKIE_MINUTES, '/', null, null, true, false, 'Lax'));
        }

        return $id;
    }

    public static function isBot(?string $userAgent): bool
    {
        $ua = trim((string) $userAgent);

        return $ua === '' || preg_match(self::BOT_PATTERN, $ua) === 1;
    }

    /**
     * @param  array<string, mixed>  $extra  platform, source, app_version, path, device_id, browser_id
     */
    public static function record(string $type, Request $request, array $extra = []): void
    {
        try {
            $ua = (string) $request->userAgent();
            $deviceId = isset($extra['device_id']) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', (string) $extra['device_id'])
                ? 'd:'.$extra['device_id'] : null;
            $browserId = $extra['browser_id'] ?? (string) $request->cookie(self::COOKIE, '');

            $raw = $deviceId
                ?? ($browserId !== '' ? 'c:'.$browserId : 'ip:'.$request->ip().'|'.$ua);
            $visitor = hash_hmac('sha256', $raw, (string) config('app.key'));

            $isNew = ! AnalyticsEvent::where('type', $type)->where('visitor', $visitor)->exists();
            $agent = self::parseUserAgent($ua);

            AnalyticsEvent::create([
                'type' => $type,
                'visitor' => $visitor,
                'new_visitor' => $isNew,
                'platform' => $extra['platform'] ?? null,
                'source' => $extra['source'] ?? null,
                'app_version' => isset($extra['app_version']) ? Str::limit((string) $extra['app_version'], 20, '') : null,
                'path' => isset($extra['path']) ? Str::limit((string) $extra['path'], 255, '') : null,
                'referrer' => self::referrerHost($request),
                'device' => $agent['device'],
                'os' => $agent['os'],
                'browser' => $agent['browser'],
                'user_id' => $request->user()?->getKey(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Rough device / OS / browser from a user agent. Good enough for a report;
     * no third-party parser needed.
     *
     * @return array{device:string, os:string, browser:string}
     */
    public static function parseUserAgent(string $ua): array
    {
        $isApp = str_contains($ua, 'MHubApp') || str_starts_with($ua, 'Dart/');

        $os = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Mac OS X|Macintosh|macOS/i', $ua) => 'macOS',
            (bool) preg_match('/Linux|X11/i', $ua) => 'Linux',
            default => 'Other',
        };

        $device = match (true) {
            $isApp => 'app',
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua),
            preg_match('/Android/i', $ua) && ! preg_match('/Mobile/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android|Opera Mini|IEMobile/i', $ua) => 'mobile',
            default => 'desktop',
        };

        $browser = match (true) {
            $isApp => 'MHub app',
            (bool) preg_match('/Edg(e|A|iOS)?\//', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung',
            (bool) preg_match('/UCBrowser/', $ua) => 'UC Browser',
            (bool) preg_match('/Firefox\/|FxiOS/', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS/', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//', $ua) => 'Safari',
            default => 'Other',
        };

        return ['device' => $device, 'os' => $os, 'browser' => $browser];
    }

    private static function referrerHost(Request $request): ?string
    {
        $ref = (string) $request->headers->get('referer', '');
        $host = $ref !== '' ? strtolower((string) parse_url($ref, PHP_URL_HOST)) : '';
        if ($host === '' || $host === strtolower($request->getHost())) {
            return null;
        }

        return Str::limit(preg_replace('/^www\./', '', $host), 120, '');
    }
}
