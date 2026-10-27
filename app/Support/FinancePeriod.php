<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The period a Finance page shows, on the business's clock (FinanceDates): a day, week, month or year (with ‹ › to
 * step back and forward), all time, or a custom from–to range. Read from the
 * query string (?period=month&date=2026-10-01, or ?period=custom&from=…&to=…).
 */
class FinancePeriod
{
    public const PERIODS = [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
        'all' => 'All time',
        'custom' => 'Custom',
    ];

    private function __construct(
        public readonly string $key,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
        public readonly Carbon $anchor,
    ) {
    }

    public static function fromRequest(Request $request, string $default = 'all'): self
    {
        $key = (string) $request->query('period', $default);
        if (! array_key_exists($key, self::PERIODS)) {
            $key = $default;
        }

        $anchor = self::date($request->query('date')) ?? FinanceDates::now();

        if ($key === 'custom') {
            $from = self::date($request->query('from'));
            $to = self::date($request->query('to'));
            if (! $from && ! $to) {
                return new self('all', null, null, $anchor);
            }
            if ($from && $to && $from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return new self('custom', $from?->startOfDay(), $to?->endOfDay(), $anchor);
        }

        return self::make($key, $anchor);
    }

    public static function make(string $key, Carbon $anchor): self
    {
        $a = $anchor->copy();

        return match ($key) {
            'day' => new self('day', $a->copy()->startOfDay(), $a->copy()->endOfDay(), $a),
            'week' => new self('week', $a->copy()->startOfWeek(Carbon::MONDAY), $a->copy()->endOfWeek(Carbon::SUNDAY), $a),
            'month' => new self('month', $a->copy()->startOfMonth(), $a->copy()->endOfMonth(), $a),
            'year' => new self('year', $a->copy()->startOfYear(), $a->copy()->endOfYear(), $a),
            default => new self('all', null, null, $a),
        };
    }

    public function isAll(): bool
    {
        return $this->from === null && $this->to === null;
    }

    /** Day, week, month and year can step back and forward. */
    public function steps(): bool
    {
        return in_array($this->key, ['day', 'week', 'month', 'year'], true);
    }

    /** Query string for the period one step back (-1) or forward (+1). */
    public function shifted(int $by): array
    {
        $a = $this->anchor->copy();
        $a = match ($this->key) {
            'day' => $a->addDays($by),
            'week' => $a->addWeeks($by),
            'month' => $a->addMonthsNoOverflow($by),
            'year' => $a->addYears($by),
            default => $a,
        };

        return ['period' => $this->key, 'date' => $a->toDateString()];
    }

    /** True when stepping forward would only show the future. */
    public function isCurrent(): bool
    {
        return $this->to === null || $this->to->gte(FinanceDates::now());
    }

    public function label(): string
    {
        return match ($this->key) {
            'day' => $this->from->toDateString() === FinanceDates::today() ? 'Today, '.$this->from->format('d M Y') : $this->from->format('D, d M Y'),
            'week' => $this->from->format('d M').' – '.$this->to->format('d M Y'),
            'month' => $this->from->format('F Y'),
            'year' => $this->from->format('Y'),
            'custom' => ($this->from?->format('d M Y') ?? 'Start').' – '.($this->to?->format('d M Y') ?? 'today'),
            default => 'All time',
        };
    }

    /** Is this moment (see FinanceDates for which date each record counts on) inside the period? */
    public function contains(?Carbon $at): bool
    {
        if ($this->isAll()) {
            return true;
        }
        if ($at === null) {
            return false;
        }

        return (! $this->from || $at->gte($this->from)) && (! $this->to || $at->lte($this->to));
    }

    private static function date($value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value, FinanceDates::tz())->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
