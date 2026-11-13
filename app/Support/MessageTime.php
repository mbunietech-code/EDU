<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * When a chat message was sent, shown under every message: day, date, month
 * and time in Tanzania time, e.g. "Fri, 13 Nov · 09:15" (the year is added
 * when it is not this year: "Mon, 29 Dec 2025 · 18:40").
 */
class MessageTime
{
    public static function label(?CarbonInterface $at): string
    {
        if (! $at) {
            return '';
        }

        $tz = (string) config('app.business_timezone', 'Africa/Dar_es_Salaam');
        $local = $at->copy()->timezone($tz);
        $sameYear = $local->year === now($tz)->year;

        return $local->format($sameYear ? 'D, d M · H:i' : 'D, d M Y · H:i');
    }
}
