<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceLoanRepayment;
use App\Support\FinanceDates;
use App\Support\FinanceStart;
use Illuminate\Support\Collection;

/**
 * Repaying capital that was recorded as a loan. Shared by web and app.
 */
class LoanService
{
    /**
     * @return array{0: array, 1: array} rules and messages for a repayment
     */
    public function repaymentRules(FinanceCapitalEntry $loan): array
    {
        $outstanding = $loan->outstandingAmount();

        return [
            MoneyAccountService::fieldRules() + [
                'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$outstanding],
                'paid_at' => array_merge(['required', 'date', 'before_or_equal:'.FinanceDates::today()], FinanceStart::rule()),
                'method' => ['nullable', 'string', 'max:120'],
                'reference' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            ['amount.max' => 'Amount cannot be more than the outstanding balance (TZS '.number_format($outstanding).').'],
        ];
    }

    public function repay(FinanceCapitalEntry $loan, array $validated, ?int $userId): FinanceLoanRepayment
    {
        $repayment = $loan->repayments()->create($validated + ['created_by' => $userId]);

        ActivityLog::log('finance_loan_repaid', 'FinanceCapitalEntry', $loan->id, [
            'repayment_id' => $repayment->id,
            'amount' => $validated['amount'],
            'outstanding' => $loan->outstandingAmount(),
        ]);

        return $repayment;
    }

    /**
     * In the books: still owed from before the Finance start date (brought),
     * borrowed and repaid since, and outstanding now.
     *
     * @return array{brought: float, borrowed: float, repaid: float, outstanding: float}
     */
    public function totals(): array
    {
        $loans = FinanceCapitalEntry::where('is_loan', true)->get();
        $totals = [
            'brought' => round((float) $this->broughtForward()->sum('outstanding'), 2),
            'borrowed' => (float) $loans->filter(fn ($c) => FinanceStart::keeps(FinanceDates::capital($c)))->sum('amount'),
            'repaid' => (float) FinanceLoanRepayment::all()->filter(fn ($r) => FinanceStart::keeps(FinanceDates::repayment($r)))->sum('amount'),
        ];
        $totals['outstanding'] = max(0, round($totals['brought'] + $totals['borrowed'] - $totals['repaid'], 2));

        return $totals;
    }

    /**
     * Loans received before the Finance start date and still owed on it, with
     * what was owed then. Empty with no start date.
     *
     * @return Collection<int, array{loan: FinanceCapitalEntry, outstanding: float}>
     */
    public function broughtForward(): Collection
    {
        if (! FinanceStart::isSet()) {
            return collect();
        }

        return FinanceCapitalEntry::where('is_loan', true)->with('repayments')->orderBy('id')->get()
            ->filter(fn ($c) => FinanceStart::isOld(FinanceDates::capital($c)))
            ->map(fn ($c) => [
                'loan' => $c,
                'outstanding' => max(0.0, round((float) $c->amount - (float) $c->repayments
                    ->filter(fn ($r) => FinanceStart::isOld(FinanceDates::repayment($r)))->sum('amount'), 2)),
            ])
            ->filter(fn ($l) => $l['outstanding'] > 0)
            ->values();
    }
}
