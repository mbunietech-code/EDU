<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Support\Analytics;
use App\Support\AppDownloads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Counted app downloads and app opens for Reports -> Analytics.
 */
class AppDownloadController extends Controller
{
    /**
     * GET /app/download/{platform}: count the download, then send the person
     * to the real file / store link. ?src=update marks in-app updates.
     */
    public function download(Request $request, string $platform): RedirectResponse
    {
        $row = collect(AppDownloads::available())->firstWhere('platform', $platform);
        abort_unless($row, 404);

        if (! Analytics::isBot($request->userAgent())) {
            Analytics::record(AnalyticsEvent::DOWNLOAD, $request, [
                'platform' => $platform,
                'source' => $request->query('src') === 'update' ? 'update' : 'web',
                'app_version' => $request->query('v') ?: ltrim((string) ($row['version'] ?? ''), 'vV') ?: null,
                'device_id' => $request->query('d'),
            ]);
        }

        return redirect()->away($row['url']);
    }

    /**
     * GET /api/app/latest: the app's update check, run once each time the app
     * starts — so it also counts app opens. Newer apps send ?platform, ?v and
     * a random ?device id; older ones are told apart by IP + user agent.
     */
    public function latest(Request $request): JsonResponse
    {
        $platform = (string) $request->query('platform', '');
        $version = $request->query('v');
        if (! $version && preg_match('/MHubApp\/([0-9][0-9A-Za-z.+-]*)/', (string) $request->userAgent(), $m)) {
            $version = $m[1];
        }

        Analytics::record(AnalyticsEvent::APP_OPEN, $request, [
            'platform' => array_key_exists($platform, AppDownloads::PLATFORMS) ? $platform : null,
            'app_version' => $version,
            'device_id' => $request->query('device'),
        ]);

        return response()->json(['platforms' => AppDownloads::latest()]);
    }
}
