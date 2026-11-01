<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The day Finance's books start (setting "finance_start_date"). Records dated
 * before it are never deleted or changed: they stay out of every Finance
 * total, statement, ledger and report, and show read-only under
 * "Unorganised data". Customer payments, orders and subscriptions are only
 * read, on the day the customer paid (FinanceDates). With no start date
 * everything counts, as before.
 *
 * Each money account starts that day with the money counted in it then
 * (setting "finance_start_balances": account id => amount).
 */
class FinanceStart
{
    public const DATE = 'finance_start_date';

    public const BALANCES = 'finance_start_balances';

    private static bool $loaded = false;

    private static ?Carbon $date = null;

    /** @var array<int, float> */
    private static array $balances = [];

    /** The first day of the books (midnight, business time), or null when not set. */
    public static function date(): ?Carbon
    {
        self::load();

        return self::$date?->copy();
    }

    public static function isSet(): bool
    {
        return self::date() !== null;
    }

    /** "01 Nov 2026", for messages. */
    public static function label(): ?string
    {
        return self::date()?->format('d M Y');
    }

    /**
     * Does a record on this moment belong to the books ($old = false) or to
     * the unorganised data before them ($old = true)? A record with no date
     * belongs to the books.
     */
    public static function keeps(?Carbon $at, bool $old = false): bool
    {
        $start = self::date();
        if ($start === null) {
            return ! $old;
        }
        $before = $at !== null && $at->lt($start);

        return $old ? $before : ! $before;
    }

    public static function isOld(?Carbon $at): bool
    {
        return self::keeps($at, true);
    }

    /**
     * Limit a query on a timestamp (stored on the server's clock) to the
     * books or to before them. Payments: created_at, the day the customer paid.
     */
    public static function scopeMoment($query, string $column = 'created_at', bool $old = false)
    {
        $start = self::date();
        if ($start === null) {
            return $old ? $query->whereRaw('1 = 0') : $query;
        }
        $bound = $start->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

        return $query->where($column, $old ? '<' : '>=', $bound);
    }

    /** The same for a date-only column; a row with no date belongs to the books. */
    public static function scopeDay($query, string $column, bool $old = false)
    {
        $start = self::date();
        if ($start === null) {
            return $old ? $query->whereRaw('1 = 0') : $query;
        }
        $day = $start->toDateString();

        return $old
            ? $query->where($column, '<', $day)
            : $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, '>=', $day));
    }

    /** @return array<int, float> account id => money counted in it on the start day */
    public static function balances(): array
    {
        self::load();

        return self::$balances;
    }

    public static function balanceFor(int $accountId): ?float
    {
        return self::balances()[$accountId] ?? null;
    }

    /** @param  array<int, float|int|string|null>  $balances */
    public static function save(string $date, array $balances): void
    {
        $clean = [];
        foreach ($balances as $id => $amount) {
            if ($amount !== null && $amount !== '' && is_numeric($amount)) {
                $clean[(int) $id] = round((float) $amount, 2);
            }
        }

        Setting::set(self::DATE, $date, 'string', 'finance');
        Setting::set(self::BALANCES, json_encode($clean, JSON_FORCE_OBJECT), 'json', 'finance');
        self::forget();
    }

    /** Count every record again, as before there was a start date. */
    public static function clear(): void
    {
        Setting::set(self::DATE, '', 'string', 'finance');
        self::forget();
    }

    /** Validation rule for a date field: on or after the start. */
    public static function rule(): array
    {
        $start = self::date();
        if ($start === null) {
            return [];
        }

        return [function (string $attribute, $value, \Closure $fail) use ($start) {
            $day = FinanceDates::day($value);
            if ($day && $day->lt($start)) {
                $fail('Finance starts on '.$start->format('d M Y').'. Earlier records stay under Unorganised data.');
            }
        }];
    }

    /** A record dated before the start stays as it was: refuse the change. */
    public static function guard(?Carbon $at, string $field = 'date'): void
    {
        if (self::isOld($at)) {
            throw ValidationException::withMessages([
                $field => 'This record is from before '.self::label().', when Finance started again. It stays as it was under Unorganised data.',
            ]);
        }
    }

    public static function forget(): void
    {
        self::$loaded = false;
        self::$date = null;
        self::$balances = [];
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        try {
            self::$date = FinanceDates::day(Setting::get(self::DATE));
            $saved = json_decode((string) Setting::get(self::BALANCES, ''), true);
        } catch (\Throwable $e) {
            return;
        }

        foreach (is_array($saved) ? $saved : [] as $id => $amount) {
            if (is_numeric($amount)) {
                self::$balances[(int) $id] = (float) $amount;
            }
        }
    }
}
