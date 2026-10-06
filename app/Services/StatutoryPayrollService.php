<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Tanzania statutory payroll: PAYE, NSSF, SDL, WCF and monthly provisions.
 * Defaults live in config/finance.php; admins can override rates in settings.
 */
class StatutoryPayrollService
{
    public const SETTING_PREFIX = 'finance_statutory_';

    public function rates(): array
    {
        $rates = [];

        foreach (config('finance.statutory.rates') as $key => $default) {
            $rates[$key] = (float) Setting::get(self::SETTING_PREFIX.$key, $default);
        }

        return $rates;
    }

    public function saveRates(array $rates): void
    {
        foreach (array_keys(config('finance.statutory.rates')) as $key) {
            if (array_key_exists($key, $rates) && $rates[$key] !== null) {
                Setting::set(self::SETTING_PREFIX.$key, (string) $rates[$key], 'string', 'finance');
            }
        }
    }

    /**
     * Monthly PAYE on taxable pay (gross minus the staff NSSF contribution).
     */
    public function paye(float $taxable): float
    {
        $tax = 0.0;

        foreach (config('finance.statutory.paye_bands') as [$from, $base, $rate]) {
            if ($taxable > $from) {
                $tax = $base + ($taxable - $from) * $rate / 100;
            }
        }

        return round($tax, 2);
    }

    /**
     * Statutory amounts for one staff member for one month.
     */
    public function forStaff(float $basic, float $gross, int $staffCount, ?array $rates = null): array
    {
        $rates ??= $this->rates();

        $nssfEmployee = round($gross * $rates['nssf_employee'] / 100, 2);
        $taxable = max(0, $gross - $nssfEmployee);
        $sdlApplies = $staffCount >= (int) $rates['sdl_min_staff'];

        $statutory = [
            'taxable_pay' => $taxable,
            'paye' => $this->paye($taxable),
            'nssf_employee' => $nssfEmployee,
            'nssf_employer' => round($gross * $rates['nssf_employer'] / 100, 2),
            'sdl' => $sdlApplies ? round($gross * $rates['sdl'] / 100, 2) : 0.0,
            'wcf' => round($gross * $rates['wcf'] / 100, 2),
            'leave_provision' => round($basic * $rates['leave_provision'] / 100, 2),
            'severance_provision' => round($basic * $rates['severance_provision'] / 100, 2),
            'gratuity_provision' => round($basic * $rates['gratuity_provision'] / 100, 2),
        ];

        $statutory['employer_cost'] = $gross + $statutory['nssf_employer'] + $statutory['sdl'] + $statutory['wcf'];
        $statutory['provisions'] = $statutory['leave_provision'] + $statutory['severance_provision'] + $statutory['gratuity_provision'];

        return $statutory;
    }

    public function dueDate(string $type, Carbon $month): Carbon
    {
        $next = $month->copy()->startOfMonth()->addMonthNoOverflow();

        return in_array($type, ['paye', 'sdl'], true)
            ? $next->day(7)
            : $next->endOfMonth()->startOfDay();
    }
}
