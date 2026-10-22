<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\FinanceExpense;

/**
 * Expenses Finance records by itself when money leaves for a known reason:
 * a paid payroll, a paid statutory return, a bought product account or a
 * company asset. Each source makes at most one expense (found again by
 * source_type + source_id), so pressing a button twice never doubles a cost.
 */
class AutoExpenseService
{
    /**
     * @param  array{label: string, amount: float, spent_at: mixed, expense_type?: ?string, category?: ?string,
     *               description?: ?string, product_id?: ?int, money_account_id?: ?int}  $attrs
     */
    public function record(string $sourceType, int $sourceId, array $attrs, ?int $userId): FinanceExpense
    {
        $existing = $this->find($sourceType, $sourceId);
        if ($existing) {
            return $existing;
        }

        $type = FinanceExpense::types()[$attrs['expense_type'] ?? ''] ?? null;
        $data = [
            'label' => $attrs['label'],
            'amount' => round((float) $attrs['amount'], 2),
            'spent_at' => $attrs['spent_at'],
            'category' => $attrs['category'] ?? $type['category'] ?? null,
            'description' => $attrs['description'] ?? null,
            'product_id' => $attrs['product_id'] ?? null,
            'created_by' => $userId,
        ];

        if (FinanceExpense::typesReady()) {
            $data += ['expense_type' => $attrs['expense_type'] ?? null, 'source_type' => $sourceType, 'source_id' => $sourceId];
        }
        if (MoneyAccountService::ready() && ! empty($attrs['money_account_id'])) {
            $data['money_account_id'] = (int) $attrs['money_account_id'];
        }

        $expense = FinanceExpense::create($data);

        ActivityLog::log('finance_expense_auto', 'FinanceExpense', $expense->id, [
            'source' => $sourceType, 'source_id' => $sourceId, 'amount' => $expense->amount,
        ]);

        return $expense;
    }

    public function find(string $sourceType, int $sourceId): ?FinanceExpense
    {
        return FinanceExpense::typesReady()
            ? FinanceExpense::where('source_type', $sourceType)->where('source_id', $sourceId)->first()
            : null;
    }

    /** Remove the automatic expense of a source that was undone (e.g. a return set back to pending). */
    public function forget(string $sourceType, int $sourceId): void
    {
        $expense = $this->find($sourceType, $sourceId);
        if ($expense) {
            ActivityLog::log('finance_expense_auto_removed', 'FinanceExpense', $expense->id, [
                'source' => $sourceType, 'source_id' => $sourceId, 'amount' => $expense->amount,
            ]);
            $expense->delete();
        }
    }
}
