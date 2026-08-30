<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Site logo + favicon, uploaded by an admin in Settings → Branding.
 * Falls back to the built-in "M" mark / packaged favicon when unset.
 */
class Branding
{
    public const LOGO_KEY = 'site_logo_path';
    public const FAVICON_KEY = 'site_favicon_path';
    public const GROUP = 'branding';

    private const CACHE_KEY = 'branding.paths';

    public static function logoUrl(): ?string
    {
        return self::urlFor(self::LOGO_KEY);
    }

    public static function faviconUrl(): ?string
    {
        return self::urlFor(self::FAVICON_KEY);
    }

    public static function hasLogo(): bool
    {
        return self::logoUrl() !== null;
    }

    public static function hasFavicon(): bool
    {
        return self::faviconUrl() !== null;
    }

    /**
     * @return array{site_logo_path:?string, site_favicon_path:?string}
     */
    public static function paths(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                return [
                    self::LOGO_KEY => Setting::get(self::LOGO_KEY) ?: null,
                    self::FAVICON_KEY => Setting::get(self::FAVICON_KEY) ?: null,
                ];
            });
        } catch (Throwable $e) {
            // DB not ready (e.g. during setup) — behave as if nothing is set.
            return [self::LOGO_KEY => null, self::FAVICON_KEY => null];
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function urlFor(string $key): ?string
    {
        $path = self::paths()[$key] ?? null;
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                return null;
            }

            return $disk->url($path);
        } catch (Throwable $e) {
            return null;
        }
    }
}
