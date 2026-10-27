<?php

namespace App\Support;

use App\Models\FinanceCapitalEntry;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * The dates Finance counts money on, in the business's own time zone
 * (Tanzania, UTC+3) rather than the server's UTC — otherwise a sale or a
 * cost near midnight lands on the wrong day, and "today" starts at 03:00.
 *
 *  - a customer payment: the day the customer paid (when it was made), not
 *    the day an admin approved it;
 *  - capital / a loan: the date the money was received, as typed (older
 *    entries without one: the day they were recorded);
 *  - expenses and repayments: their own date fields, as typed.
 */
class FinanceDates
{
    private static ?bool $receivedColumn = null;

    public static function tz(): string
    {
        return (string) config('app.business_timezone', 'Africa/Dar_es_Salaam');
    }

    /** Now, on the business's clock. */
    public static function now(): Carbon
    {
        return Carbon::now(self::tz());
    }

    /** Today's date (Y-m-d) on the business's clock — the default for date fields. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /** A stored timestamp (server time) seen on the business's clock. */
    public static function local($value): ?Carbon
    {
        $at = Dates::carbon($value);

        return $at ? $at->copy()->setTimezone(self::tz()) : null;
    }

    /** A date-only value ("2026-10-27") as that day on the business's clock. */
    public static function day($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);

        try {
            return Carbon::createFromFormat('Y-m-d', $date, self::tz())->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function payment(Payment $payment): ?Carbon
    {
        return self::local($payment->created_at ?? $payment->reviewed_at);
    }

    public static function capital(FinanceCapitalEntry $entry): ?Carbon
    {
        if (self::hasReceivedDate() && $entry->received_at) {
            return self::day($entry->received_at);
        }

        return self::local($entry->created_at);
    }

    /** False until database/alters/0036 (received_at on capital entries) is applied. */
    public static function hasReceivedDate(): bool
    {
        if (self::$receivedColumn === null) {
            try {
                self::$receivedColumn = Schema::hasColumn('finance_capital_entries', 'received_at');
            } catch (\Throwable $e) {
                self::$receivedColumn = false;
            }
        }

        return self::$receivedColumn;
    }

    public static function forget(): void
    {
        self::$receivedColumn = null;
    }
}
