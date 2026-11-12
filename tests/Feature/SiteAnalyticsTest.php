<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnalyticsReport;
use App\Support\Analytics;
use App\Support\AppDownloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36';

    private function setAndroidDownload(): void
    {
        Setting::set(AppDownloads::key('android', 'url'), 'https://example.com/MHub.apk', 'string', AppDownloads::GROUP);
        Setting::set(AppDownloads::key('android', 'version'), 'v1.2.3', 'string', AppDownloads::GROUP);
        AppDownloads::forget();
    }

    public function test_a_page_view_is_recorded_with_a_device_cookie_and_the_same_device_counts_once(): void
    {
        $first = $this->withHeaders(['User-Agent' => self::BROWSER])->get('/');
        $first->assertOk()->assertCookie(Analytics::COOKIE);

        $this->assertSame(1, AnalyticsEvent::where('type', 'page_view')->count());
        $event = AnalyticsEvent::first();
        $this->assertSame('/', $event->path);
        $this->assertSame('mobile', $event->device);
        $this->assertSame('Android', $event->os);
        $this->assertSame('Chrome', $event->browser);
        $this->assertTrue($event->new_visitor);

        // Same browser comes back: a second view, still one device.
        $id = AnalyticsEvent::first()->visitor;
        $cookie = $first->getCookie(Analytics::COOKIE, false)->getValue();
        $this->withUnencryptedCookie(Analytics::COOKIE, $cookie)
            ->withHeaders(['User-Agent' => self::BROWSER])->get('/')->assertOk();

        $this->assertSame(2, AnalyticsEvent::where('type', 'page_view')->count());
        $this->assertSame(1, AnalyticsEvent::distinct()->count('visitor'));
        $this->assertFalse(AnalyticsEvent::latest('id')->first()->new_visitor);
        $this->assertSame($id, AnalyticsEvent::latest('id')->first()->visitor);
    }

    public function test_bots_background_requests_and_admins_are_not_counted(): void
    {
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get('/')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Sec-Fetch-Dest' => 'empty'])->get('/')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'X-Requested-With' => 'XMLHttpRequest'])->get('/');
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->withHeaders(['User-Agent' => self::BROWSER])->get('/')->assertOk();

        $this->assertSame(0, AnalyticsEvent::count());
    }

    public function test_download_link_counts_and_redirects_to_the_real_file(): void
    {
        $this->setAndroidDownload();

        $this->withHeaders(['User-Agent' => self::BROWSER])
            ->get(route('app.download', 'android'))
            ->assertRedirect('https://example.com/MHub.apk');

        $event = AnalyticsEvent::where('type', 'download')->first();
        $this->assertNotNull($event);
        $this->assertSame('android', $event->platform);
        $this->assertSame('web', $event->source);
        $this->assertSame('1.2.3', $event->app_version);

        // A platform with no link set is a 404 and counts nothing.
        $this->get(route('app.download', 'linux'))->assertNotFound();
        $this->assertSame(1, AnalyticsEvent::where('type', 'download')->count());
    }

    public function test_the_footer_and_update_check_use_the_counted_download_link(): void
    {
        $this->setAndroidDownload();

        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/')
            ->assertOk()
            ->assertSee(route('app.download', 'android'), false);

        $latest = $this->getJson('/api/app/latest?platform=android&v=1.2.4&device=abcdefghij1234567890')
            ->assertOk()
            ->json('platforms.android');
        $this->assertSame('1.2.3', $latest['version']);
        $this->assertSame(route('app.download', ['platform' => 'android', 'src' => 'update']), $latest['url']);
    }

    public function test_app_opens_are_counted_per_install_id(): void
    {
        foreach (['device-one-123456', 'device-one-123456', 'device-two-123456'] as $device) {
            $this->withHeaders(['User-Agent' => 'MHubApp/1.2.4 (+https://mbuniehub.com)'])
                ->getJson('/api/app/latest?platform=windows&v=1.2.4&device='.$device)
                ->assertOk();
        }
        // An older app: no query, version taken from the user agent.
        $this->withHeaders(['User-Agent' => 'MHubApp/1.2.3 (+https://mbuniehub.com)'])->getJson('/api/app/latest')->assertOk();

        $opens = AnalyticsEvent::where('type', 'app_open');
        $this->assertSame(4, (clone $opens)->count());
        $this->assertSame(3, (clone $opens)->distinct()->count('visitor'));
        $this->assertSame(3, (clone $opens)->where('platform', 'windows')->count());
        $this->assertSame('1.2.3', (clone $opens)->whereNull('platform')->first()->app_version);
        $this->assertSame('app', (clone $opens)->first()->device);
    }

    public function test_report_groups_totals_and_unique_devices_by_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 09:30:00', 'UTC')); // 12:30 in Dar es Salaam

        $make = fn (string $type, string $visitor, string $utc, bool $new = false) => AnalyticsEvent::create([
            'type' => $type, 'visitor' => $visitor, 'new_visitor' => $new,
            // Stored like now() would store it: in the app time zone.
            'created_at' => Carbon::parse($utc, 'UTC')->setTimezone(config('app.timezone')),
        ]);
        $make('page_view', 'a', '2026-11-12 09:10:00', true);
        $make('page_view', 'a', '2026-11-12 09:20:00');
        $make('page_view', 'b', '2026-11-12 08:05:00', true);
        $make('page_view', 'c', '2026-11-10 08:00:00', true);
        $make('download', 'a', '2026-11-12 09:25:00', true);
        $make('page_view', 'z', '2025-03-01 10:00:00', true);

        $report = app(AnalyticsReport::class);

        // Today, per hour (local time): 11:00 has b, 12:00 has a twice.
        $w = $report->window('today');
        $series = collect($report->series($w))->keyBy('key');
        $this->assertSame(['total' => 2, 'unique' => 1], $series['2026-11-12 12']['stats']['page_view']);
        $this->assertSame(['total' => 1, 'unique' => 1], $series['2026-11-12 11']['stats']['page_view']);
        $this->assertSame(['total' => 1, 'unique' => 1], $series['2026-11-12 12']['stats']['download']);
        $this->assertCount(13, $series); // 00:00 .. 12:00

        $totals = $report->totals($w);
        $this->assertSame(3, $totals['page_view']['total']);
        $this->assertSame(2, $totals['page_view']['unique']);
        $this->assertSame(2, $totals['page_view']['new']);

        // Last 60 minutes, per minute: a at 12:10 and 12:20 local.
        $minutes = collect($report->series($report->window('minutes')))->keyBy('key');
        $this->assertSame(1, $minutes['2026-11-12 12:10']['stats']['page_view']['total']);
        $this->assertSame(1, $minutes['2026-11-12 12:20']['stats']['page_view']['total']);

        // Per day and per year.
        $days = collect($report->series($report->window('days')))->keyBy('key');
        $this->assertSame(1, $days['2026-11-10']['stats']['page_view']['total']);
        $years = collect($report->series($report->window('years')))->keyBy('key');
        $this->assertSame(1, $years['2025']['stats']['page_view']['total']);
        $this->assertSame(['total' => 4, 'unique' => 3], $years['2026']['stats']['page_view']);

        // Summary: today vs all time; returning device counted.
        $summary = $report->summary();
        $this->assertSame(3, $summary['today']['stats']['page_view']['total']);
        $this->assertSame(5, $summary['all']['stats']['page_view']['total']);
        $this->assertSame(4, $summary['all']['stats']['page_view']['unique']);

        Carbon::setTestNow();
    }

    public function test_admin_sees_the_analytics_page_for_every_range(): void
    {
        $this->setAndroidDownload();
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/');
        $this->withHeaders(['User-Agent' => self::BROWSER])->get(route('app.download', 'android'));

        $admin = User::factory()->create(['is_admin' => true]);
        foreach (array_keys(AnalyticsReport::RANGES) as $range) {
            $this->actingAs($admin)
                ->get(route('admin.reports.analytics', ['range' => $range, 'from' => '2026-11-01', 'to' => '2026-11-12']))
                ->assertOk()
                ->assertSee('Analytics')
                ->assertSee('Most active devices');
        }

        $this->actingAs(User::factory()->create())
            ->get(route('admin.reports.analytics'))
            ->assertForbidden();
    }
}
