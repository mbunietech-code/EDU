<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceLoanRepayment;

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
            [
                'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$outstanding],
                'paid_at' => ['required', 'date', 'before_or_equal:today'],
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

    public function totals(): array
    {
        $totals = [
            'borrowed' => (float) FinanceCapitalEntry::where('is_loan', true)->sum('amount'),
            'repaid' => (float) FinanceLoanRepayment::sum('amount'),
        ];
        $totals['outstanding'] = max(0, $totals['borrowed'] - $totals['repaid']);

        return $totals;
    }
}
