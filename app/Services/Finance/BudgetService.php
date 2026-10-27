<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\FinanceDates;
use Illuminate\Support\Carbon;

/**
 * Monthly budget (Mwongozo wa Fedha §7): the month's income split by bucket
 * percentages, against what was actually spent from each bucket. Percentages
 * live in settings (finance_budget_percents); defaults come from config.
 */
class BudgetService
{
    public const SETTING = 'finance_budget_percents';

    /** @return array<string, array{label: string, percent: float, note: string, types: list<string>, categories: list<string>, repayments?: bool}> */
    public function buckets(): array
    {
        $buckets = (array) config('finance.budget_buckets', []);
        $saved = json_decode((string) Setting::get(self::SETTING, ''), true);

        if (is_array($saved)) {
            foreach ($buckets as $key => &$b) {
                if (isset($saved[$key]) && is_numeric($saved[$key])) {
                    $b['percent'] = (float) $saved[$key];
                }
            }
            unset($b);
        }

        return $buckets;
    }

    public function percentRules(): array
    {
        $rules = [];
        foreach (array_keys($this->buckets()) as $key) {
            $rules["percents.{$key}"] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    /** @throws FinanceException when the percentages do not add up to 100 */
    public function savePercents(array $percents): void
    {
        $clean = [];
        foreach (array_keys($this->buckets()) as $key) {
            $clean[$key] = round((float) ($percents[$key] ?? 0), 2);
        }
        if (abs(array_sum($clean) - 100) > 0.001) {
            throw new FinanceException('The percentages add up to '.rtrim(rtrim(number_format(array_sum($clean), 2), '0'), '.').'%; they must add up to 100%.');
        }

        Setting::set(self::SETTING, json_encode($clean), 'json', 'finance');
        ActivityLog::log('finance_budget_updated', 'Finance', null, $clean);
    }

    /**
     * One month: income, and per bucket the budget, what was spent and what is left.
     *
     * @return array{month: string, income: float, rows: list<array>, spent: float, unbudgeted: float}
     */
    public function month(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $income = (float) Payment::where('status', 'approved')
            ->get(['amount', 'reviewed_at', 'created_at'])
            ->filter(fn ($p) => FinanceDates::payment($p)?->format('Y-m') === $from->format('Y-m'))
            ->sum('amount');

        $buckets = $this->buckets();
        $spent = array_fill_keys(array_keys($buckets), 0.0);
        $unbudgeted = 0.0;

        $expenses = FinanceExpense::whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])->get();
        foreach ($expenses as $e) {
            $key = $this->bucketFor($e, $buckets);
            if ($key === null) {
                $unbudgeted += (float) $e->amount;
            } else {
                $spent[$key] += (float) $e->amount;
            }
        }

        $repayments = (float) FinanceLoanRepayment::whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])->sum('amount');
        $repayBucket = collect($buckets)->search(fn ($b) => ! empty($b['repayments']));
        if ($repayBucket !== false) {
            $spent[$repayBucket] += $repayments;
        } else {
            $unbudgeted += $repayments;
        }

        $rows = [];
        foreach ($buckets as $key => $b) {
            $budget = round($income * $b['percent'] / 100, 2);
            $used = round($spent[$key], 2);
            $rows[] = [
                'key' => $key,
                'label' => $b['label'],
                'note' => $b['note'] ?? '',
                'percent' => $b['percent'],
                'budget' => $budget,
                'spent' => $used,
                'left' => round($budget - $used, 2),
                'used_pct' => $budget > 0 ? round($used / $budget * 100) : ($used > 0 ? null : 0),
            ];
        }

        return [
            'month' => $from->format('Y-m'),
            'income' => round($income, 2),
            'rows' => $rows,
            'spent' => round(array_sum($spent) + $unbudgeted, 2),
            'unbudgeted' => round($unbudgeted, 2),
        ];
    }

    /** Months that have income or spending, newest first. */
    public function months(): array
    {
        $dates = Payment::where('status', 'approved')->get(['reviewed_at', 'created_at'])
            ->map(fn ($p) => FinanceDates::payment($p)?->format('Y-m'))
            ->merge(FinanceExpense::pluck('spent_at')->map(fn ($d) => Carbon::parse($d)->format('Y-m')))
            ->push(FinanceDates::now()->format('Y-m'));

        return $dates->unique()->sortDesc()->values()->all();
    }

    /**
     * How capital was spread over products (Mwongozo §7, "mgawanyo wa mtaji").
     *
     * @return list<array{label: string, amount: float, share: float}>
     */
    public function capitalShares(): array
    {
        $entries = FinanceCapitalEntry::with('product:id,name', 'tool:id,name')->get();
        $total = (float) $entries->sum('amount');

        return $entries
            ->groupBy(fn ($c) => $c->product->name ?? $c->tool->name ?? $c->label)
            ->map(fn ($g, $label) => [
                'label' => $label,
                'amount' => (float) $g->sum('amount'),
                'share' => $total > 0 ? round($g->sum('amount') / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function bucketFor(FinanceExpense $e, array $buckets): ?string
    {
        if ($e->expense_type ?? null) {
            foreach ($buckets as $key => $b) {
                if (in_array($e->expense_type, $b['types'] ?? [], true)) {
                    return $key;
                }
            }
        }
        foreach ($buckets as $key => $b) {
            if ($e->category && in_array($e->category, $b['categories'] ?? [], true)) {
                return $key;
            }
        }

        return null;
    }
}
