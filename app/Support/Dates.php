<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Defensive date formatting for Blade.
 *
 * The production database is SQL-imported (see the "backend-db-sql-imported"
 * note), so a timestamp column can occasionally come back as a raw string
 * instead of a Carbon instance. Calling ->format() straight on that value
 * throws "Call to a member function format() on string". These helpers never
 * throw: they parse what they can and fall back to a dash otherwise.
 */
class Dates
{
    public static function to(mixed $value, string $format, string $fallback = '—'): string
    {
        $date = self::carbon($value);

        return $date ? $date->format($format) : $fallback;
    }

    /** "d M Y" — e.g. 31 Aug 2026. */
    public static function human(mixed $value, string $fallback = '—'): string
    {
        return self::to($value, 'd M Y', $fallback);
    }

    /** "d M Y H:i" — e.g. 31 Aug 2026 14:05. */
    public static function humanTime(mixed $value, string $fallback = '—'): string
    {
        return self::to($value, 'd M Y H:i', $fallback);
    }

    public static function carbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance(Carbon::parse($value));
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
