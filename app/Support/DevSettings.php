<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Admin-only "show error details" switch, toggled from Settings.
 *
 * When ON, signed-in admins see the full exception (message, file:line, stack
 * trace, request data) instead of the generic error page. Everyone else always
 * sees the friendly page. It never enables APP_DEBUG globally.
 */
class DevSettings
{
    public const KEY = 'admin_debug_enabled';
    public const GROUP = 'system';

    private const CACHE_KEY = 'dev.admin_debug';

    public static function adminDebugEnabled(): bool
    {
        try {
            return (bool) Cache::remember(self::CACHE_KEY, 30, function () {
                if (! Schema::hasTable('settings')) {
                    return false;
                }

                return filter_var(Setting::get(self::KEY), FILTER_VALIDATE_BOOL);
            });
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function set(bool $on): void
    {
        Setting::set(self::KEY, $on ? '1' : '0', 'boolean', self::GROUP);
        self::forget();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
