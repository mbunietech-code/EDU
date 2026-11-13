<?php

namespace Tests\Unit;

use App\Support\MessageTime;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MessageTimeTest extends TestCase
{
    public function test_label_has_day_date_month_and_time_in_tanzania_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'Africa/Dar_es_Salaam'));
        config(['app.business_timezone' => 'Africa/Dar_es_Salaam']);

        // 06:15 UTC = 09:15 in Dar es Salaam, Friday 13 November.
        $this->assertSame('Fri, 13 Nov · 09:15', MessageTime::label(Carbon::parse('2026-11-13 06:15:00', 'UTC')));

        // Another year shows the year too.
        $this->assertSame('Mon, 29 Dec 2025 · 18:40', MessageTime::label(Carbon::parse('2025-12-29 15:40:00', 'UTC')));

        $this->assertSame('', MessageTime::label(null));

        Carbon::setTestNow();
    }
}
