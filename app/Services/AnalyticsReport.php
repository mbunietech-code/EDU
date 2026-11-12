<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for Admin -> Reports -> Analytics: page views, app downloads and app
 * opens, each as a total and as unique devices, grouped by minute, hour, day,
 * month or year in the business time zone (Africa/Dar_es_Salaam).
 */
class AnalyticsReport
{
    public const TYPES = [
        AnalyticsEvent::PAGE_VIEW => 'Page views',
        AnalyticsEvent::DOWNLOAD => 'App downloads',
        AnalyticsEvent::APP_OPEN => 'App opens',
    ];

    /** Preset => [label, group by, how far back]. */
    public const RANGES = [
        'minutes' => ['Last 60 minutes (per minute)', 'minute'],
        'hours' => ['Last 24 hours (per hour)', 'hour'],
        'today' => ['Today (per hour)', 'hour'],
        'days' => ['Last 30 days (per day)', 'day'],
        'months' => ['Last 12 months (per month)', 'month'],
        'years' => ['All years (per year)', 'year'],
        'custom' => ['Custom dates', null],
    ];

    public const GROUPS = ['minute' => 'Minute', 'hour' => 'Hour', 'day' => 'Day', 'month' => 'Month', 'year' => 'Year'];

    /** Most buckets a chart may have (a custom range per minute is capped). */
    private const MAX_BUCKETS = 400;

    public function timezone(): string
    {
        return (string) config('app.business_timezone', 'Africa/Dar_es_Salaam');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * Resolve the filter into a local-time window and a grouping.
     *
     * @return array{range:string, group:string, from:CarbonImmutable, to:CarbonImmutable, label:string}
     */
    public function window(string $range, ?string $from = null, ?string $to = null, ?string $group = null): array
    {
        $now = $this->now();
        $range = array_key_exists($range, self::RANGES) ? $range : 'days';

        [$start, $end, $by] = match ($range) {
            'minutes' => [$now->subMinutes(59)->startOfMinute(), $now, 'minute'],
            'hours' => [$now->subHours(23)->startOfHour(), $now, 'hour'],
            'today' => [$now->startOfDay(), $now, 'hour'],
            'months' => [$now->subMonths(11)->startOfMonth(), $now, 'month'],
            'years' => [$this->firstEventYear($now), $now, 'year'],
            'custom' => $this->customWindow($now, $from, $to, $group),
            default => [$now->subDays(29)->startOfDay(), $now, 'day'],
        };

        $label = $range === 'custom'
            ? $start->format('d M Y').' – '.$end->format('d M Y').' (per '.strtolower(self::GROUPS[$by]).')'
            : self::RANGES[$range][0];

        return ['range' => $range, 'group' => $by, 'from' => $start, 'to' => $end, 'label' => $label];
    }

    /**
     * Headline cards: for each fixed window, every type's total and unique devices.
     *
     * @return array<string, array{label:string, stats:array<string, array{total:int, unique:int}>}>
     */
    public function summary(): array
    {
        $now = $this->now();
        $windows = [
            'live' => ['Live now (last 5 min)', $now->subMinutes(5)],
            'hour' => ['Last 60 minutes', $now->subHour()],
            'today' => ['Today', $now->startOfDay()],
            'month' => ['This month', $now->startOfMonth()],
            'year' => ['This year', $now->startOfYear()],
            'all' => ['All time', null],
        ];

        $out = [];
        foreach ($windows as $key => [$label, $since]) {
            $q = AnalyticsEvent::query()
                ->select('type', DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT visitor) as uniq'))
                ->groupBy('type');
            if ($since) {
                $q->where('created_at', '>=', $this->toDb($since));
            }
            $rows = $q->get()->keyBy('type');

            $stats = [];
            foreach (array_keys(self::TYPES) as $type) {
                $stats[$type] = [
                    'total' => (int) ($rows[$type]->total ?? 0),
                    'unique' => (int) ($rows[$type]->uniq ?? 0),
                ];
            }
            $out[$key] = ['label' => $label, 'stats' => $stats];
        }

        return $out;
    }

    /**
     * Totals for the window: total, unique devices, new and returning devices.
     *
     * @return array<string, array{total:int, unique:int, new:int, returning:int, users:int}>
     */
    public function totals(array $w): array
    {
        $rows = $this->inWindow($w)
            ->select(
                'type',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(DISTINCT visitor) as uniq'),
                DB::raw('SUM(CASE WHEN new_visitor = 1 THEN 1 ELSE 0 END) as fresh'),
                DB::raw('COUNT(DISTINCT user_id) as users'),
            )
            ->groupBy('type')
            ->get()->keyBy('type');

        $out = [];
        foreach (array_keys(self::TYPES) as $type) {
            $unique = (int) ($rows[$type]->uniq ?? 0);
            $new = min($unique, (int) ($rows[$type]->fresh ?? 0));
            $out[$type] = [
                'total' => (int) ($rows[$type]->total ?? 0),
                'unique' => $unique,
                'new' => $new,
                'returning' => $unique - $new,
                'users' => (int) ($rows[$type]->users ?? 0),
            ];
        }

        return $out;
    }

    /**
     * One row per minute / hour / day / month / year in the window (empty
     * ones included), oldest first.
     *
     * @return list<array{key:string, label:string, stats:array<string, array{total:int, unique:int}>}>
     */
    public function series(array $w): array
    {
        $bucket = $this->bucketSql($w['group']);
        $rows = $this->inWindow($w)
            ->select('type', DB::raw("$bucket as bucket"), DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT visitor) as uniq'))
            ->groupBy('type', DB::raw($bucket))
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $data[(string) $r->bucket][$r->type] = ['total' => (int) $r->total, 'unique' => (int) $r->uniq];
        }

        $out = [];
        $empty = array_fill_keys(array_keys(self::TYPES), ['total' => 0, 'unique' => 0]);
        foreach ($this->buckets($w) as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label, 'stats' => array_merge($empty, $data[$key] ?? [])];
        }

        return $out;
    }

    /**
     * Breakdowns for the window.
     *
     * @return array<string, mixed>
     */
    public function breakdowns(array $w): array
    {
        $views = fn () => $this->inWindow($w)->where('type', AnalyticsEvent::PAGE_VIEW);

        $group = function ($query, string $column, int $limit = 10) {
            return $query
                ->select(DB::raw("COALESCE($column, 'Unknown') as name"), DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT visitor) as uniq'))
                ->groupBy(DB::raw("COALESCE($column, 'Unknown')"))
                ->orderByDesc('total')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => ['name' => (string) $r->name, 'total' => (int) $r->total, 'unique' => (int) $r->uniq])
                ->all();
        };

        return [
            'pages' => $group($views(), 'path', 15),
            'devices' => $group($views(), 'device'),
            'os' => $group($views(), 'os'),
            'browsers' => $group($views(), 'browser'),
            'referrers' => $group($views()->whereNotNull('referrer'), 'referrer'),
            'downloads' => $this->inWindow($w)->where('type', AnalyticsEvent::DOWNLOAD)
                ->select('platform', 'source', DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT visitor) as uniq'))
                ->groupBy('platform', 'source')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'platform' => (string) $r->platform,
                    'source' => $r->source === 'update' ? 'In-app update' : 'Website',
                    'total' => (int) $r->total,
                    'unique' => (int) $r->uniq,
                ])->all(),
            'app_platforms' => $group($this->inWindow($w)->where('type', AnalyticsEvent::APP_OPEN), 'platform'),
            'app_versions' => $group($this->inWindow($w)->where('type', AnalyticsEvent::APP_OPEN), 'app_version'),
        ];
    }

    /**
     * The devices seen most in the window: how many times each one viewed,
     * downloaded or opened the app — so a single device repeating shows up.
     *
     * @return list<array<string, mixed>>
     */
    public function topDevices(array $w, int $limit = 20): array
    {
        $tz = $this->timezone();

        return $this->inWindow($w)
            ->select(
                'visitor',
                DB::raw("SUM(CASE WHEN type = 'page_view' THEN 1 ELSE 0 END) as views"),
                DB::raw("SUM(CASE WHEN type = 'download' THEN 1 ELSE 0 END) as downloads"),
                DB::raw("SUM(CASE WHEN type = 'app_open' THEN 1 ELSE 0 END) as opens"),
                DB::raw('COUNT(*) as total'),
                DB::raw('MAX(device) as device'),
                DB::raw('MAX(os) as os'),
                DB::raw('MAX(browser) as browser'),
                DB::raw('MAX(user_id) as user_id'),
                DB::raw('MIN(created_at) as first_at'),
                DB::raw('MAX(created_at) as last_at'),
            )
            ->groupBy('visitor')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function ($r) use ($tz) {
                return [
                    'id' => strtoupper(substr((string) $r->visitor, 0, 8)),
                    'views' => (int) $r->views,
                    'downloads' => (int) $r->downloads,
                    'opens' => (int) $r->opens,
                    'total' => (int) $r->total,
                    'device' => $r->device,
                    'os' => $r->os,
                    'browser' => $r->browser,
                    'user_id' => $r->user_id ? (int) $r->user_id : null,
                    'first_at' => $this->fromDb((string) $r->first_at)->setTimezone($tz),
                    'last_at' => $this->fromDb((string) $r->last_at)->setTimezone($tz),
                ];
            })
            ->all();
    }

    // ------------------------------------------------------------------

    private function inWindow(array $w)
    {
        return AnalyticsEvent::query()
            ->where('created_at', '>=', $this->toDb($w['from']))
            ->where('created_at', '<=', $this->toDb($w['to']));
    }

    /** Local time -> the app time zone the column is stored in. */
    private function toDb(CarbonImmutable $t): string
    {
        return $t->setTimezone((string) config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
    }

    private function fromDb(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, (string) config('app.timezone', 'UTC'));
    }

    /** Seconds to add to a stored timestamp to get business local time. */
    private function offsetSeconds(): int
    {
        $now = CarbonImmutable::now();

        return $now->setTimezone($this->timezone())->getOffset()
            - $now->setTimezone((string) config('app.timezone', 'UTC'))->getOffset();
    }

    /** SQL that turns created_at into the local bucket key (same format as buckets()). */
    private function bucketSql(string $group): string
    {
        $off = $this->offsetSeconds();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $fmt = ['minute' => '%Y-%m-%d %H:%M', 'hour' => '%Y-%m-%d %H', 'day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y'][$group];

            return "strftime('$fmt', created_at, '$off seconds')";
        }

        $fmt = ['minute' => '%Y-%m-%d %H:%i', 'hour' => '%Y-%m-%d %H', 'day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y'][$group];

        return "DATE_FORMAT(DATE_ADD(created_at, INTERVAL $off SECOND), '$fmt')";
    }

    /**
     * Bucket key => label for every step of the window.
     *
     * @return array<string, string>
     */
    private function buckets(array $w): array
    {
        [$keyFmt, $labelFmt, $step] = match ($w['group']) {
            'minute' => ['Y-m-d H:i', 'H:i', 'addMinute'],
            'hour' => ['Y-m-d H', $w['to']->diffInDays($w['from'], true) >= 1 ? 'd M H:00' : 'H:00', 'addHour'],
            'day' => ['Y-m-d', 'd M', 'addDay'],
            'month' => ['Y-m', 'M Y', 'addMonth'],
            default => ['Y', 'Y', 'addYear'],
        };

        $start = match ($w['group']) {
            'minute' => $w['from']->startOfMinute(),
            'hour' => $w['from']->startOfHour(),
            'day' => $w['from']->startOfDay(),
            'month' => $w['from']->startOfMonth(),
            default => $w['from']->startOfYear(),
        };

        $out = [];
        for ($t = $start; $t <= $w['to'] && count($out) < self::MAX_BUCKETS; $t = $t->{$step}()) {
            $out[$t->format($keyFmt)] = $t->format($labelFmt);
        }

        return $out;
    }

    private function firstEventYear(CarbonImmutable $now): CarbonImmutable
    {
        $first = AnalyticsEvent::min('created_at');

        return $first ? $this->fromDb((string) $first)->setTimezone($this->timezone())->startOfYear() : $now->startOfYear();
    }

    /**
     * @return array{0:CarbonImmutable, 1:CarbonImmutable, 2:string}
     */
    private function customWindow(CarbonImmutable $now, ?string $from, ?string $to, ?string $group): array
    {
        $tz = $this->timezone();
        try {
            $start = $from ? CarbonImmutable::parse($from, $tz)->startOfDay() : $now->subDays(6)->startOfDay();
        } catch (\Throwable $e) {
            $start = $now->subDays(6)->startOfDay();
        }
        try {
            $end = $to ? CarbonImmutable::parse($to, $tz)->endOfDay() : $now;
        } catch (\Throwable $e) {
            $end = $now;
        }
        if ($end->lt($start)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $days = $start->diffInDays($end, true);
        $by = array_key_exists((string) $group, self::GROUPS)
            ? (string) $group
            : ($days <= 2 ? 'hour' : ($days <= 92 ? 'day' : ($days <= 1100 ? 'month' : 'year')));

        // Keep the chart readable: a long range per minute starts later.
        $limit = ['minute' => 'subMinutes', 'hour' => 'subHours', 'day' => 'subDays', 'month' => 'subMonths', 'year' => 'subYears'][$by];
        $earliest = $end->{$limit}(self::MAX_BUCKETS - 1);
        if ($start->lt($earliest)) {
            $start = $earliest;
        }

        return [$start, $end, $by];
    }
}
