<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Download links for the MHub cross-platform app, managed by an admin in
 * Settings → App downloads. Each platform can be a URL (e.g. a GitHub
 * release, Play Store) or an uploaded file, plus a version label.
 */
class AppDownloads
{
    public const GROUP = 'app_downloads';

    private const CACHE_KEY = 'app_downloads.list';

    /**
     * Platform key => display label.
     *
     * @var array<string,string>
     */
    public const PLATFORMS = [
        'android' => 'Android',
        'windows' => 'Windows',
        'macos' => 'macOS',
        'linux' => 'Linux',
    ];

    public static function key(string $platform, string $suffix): string
    {
        return 'app_'.$platform.'_'.$suffix;
    }

    /**
     * Available downloads only (those with a resolvable link).
     *
     * @return list<array{platform:string,label:string,url:string,version:?string}>
     */
    public static function available(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => self::build());
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function hasAny(): bool
    {
        return self::available() !== [];
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Raw settings for the admin form (all platforms, whether set or not).
     *
     * @return array<string, array{url:?string, path:?string, version:?string}>
     */
    public static function adminRows(): array
    {
        $rows = [];
        foreach (array_keys(self::PLATFORMS) as $platform) {
            $rows[$platform] = [
                'url' => Setting::get(self::key($platform, 'url')) ?: null,
                'path' => Setting::get(self::key($platform, 'path')) ?: null,
                'version' => Setting::get(self::key($platform, 'version')) ?: null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{platform:string,label:string,url:string,version:?string}>
     */
    private static function build(): array
    {
        $out = [];
        $disk = Storage::disk('public');

        foreach (self::PLATFORMS as $platform => $label) {
            $url = Setting::get(self::key($platform, 'url')) ?: null;
            $path = Setting::get(self::key($platform, 'path')) ?: null;
            $version = Setting::get(self::key($platform, 'version')) ?: null;

            $resolved = null;
            if ($url) {
                $resolved = $url;
            } elseif ($path && $disk->exists($path)) {
                $resolved = $disk->url($path);
            }

            if ($resolved) {
                $out[] = [
                    'platform' => $platform,
                    'label' => $label,
                    'url' => $resolved,
                    'version' => $version,
                ];
            }
        }

        return $out;
    }
}
