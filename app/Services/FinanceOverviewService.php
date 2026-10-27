<?php

namespace App\Services;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tool;
use App\Support\FinancePeriod;

class FinanceOverviewService
{
    /**
     * Build a per-software finance summary: capital, income (from real
     * approved payments), expenses, loan repayments and the resulting
     * balance, plus totals. With a period, only what happened in it counts
     * (payments by approval date, capital by entry date, expenses by spend
     * date, repayments by pay date) and the balance is the period's net.
     */
    public function rows(?FinancePeriod $period = null): array
    {
        $in = fn ($at) => $period === null || $period->contains($at);

        $rows = [];

        foreach (Product::orderBy('name')->get() as $product) {
            $key = 'product:' . $product->id;
            $rows[$key] = [
                'key' => $key,
                'label' => $product->name,
                'type' => 'Product',
                'capital' => 0,
                'income' => 0,
                'expenses' => 0,
                'repaid' => 0,
            ];
        }

        foreach (Tool::orderBy('name')->get() as $tool) {
            $key = 'tool:' . $tool->id;
            $rows[$key] = [
                'key' => $key,
                'label' => $tool->name,
                'type' => 'Tool',
                'capital' => 0,
                'income' => 0,
                'expenses' => 0,
                'repaid' => 0,
            ];
        }

        // Income: real revenue already flowing through the system (approved payments).
        $incomeByProduct = Payment::where('status', 'approved')
            ->whereHas('order', fn ($q) => $q->whereNotNull('product_id'))
            ->with('order:id,product_id')
            ->get()
            ->filter(fn ($payment) => $in($payment->reviewed_at ?? $payment->created_at))
            ->groupBy(fn ($payment) => 'product:' . $payment->order->product_id)
            ->map(fn ($group) => (float) $group->sum('amount'));

        $incomeByTool = Payment::where('status', 'approved')
            ->whereHas('order', fn ($q) => $q->whereNotNull('tool_id'))
            ->with('order:id,tool_id')
            ->get()
            ->filter(fn ($payment) => $in($payment->reviewed_at ?? $payment->created_at))
            ->groupBy(fn ($payment) => 'tool:' . $payment->order->tool_id)
            ->map(fn ($group) => (float) $group->sum('amount'));

        foreach ($incomeByProduct->merge($incomeByTool) as $key => $amount) {
            if (! isset($rows[$key])) {
                continue;
            }
            $rows[$key]['income'] += $amount;
        }

        // Capital and expenses: manually recorded per software.
        foreach (FinanceCapitalEntry::all() as $entry) {
            if (! $in($entry->created_at)) {
                continue;
            }
            $key = $this->keyFor($entry->product_id, $entry->tool_id, $entry->label);
            $rows[$key] ??= $this->blankRow($entry->label);
            $rows[$key]['capital'] += (float) $entry->amount;
        }

        foreach (FinanceExpense::all() as $expense) {
            if (! $in($expense->spent_at ?? $expense->created_at)) {
                continue;
            }
            // General costs with a type share one line per type ("Electricity
            // (LUKU)"), whatever label each was typed with.
            if (! $expense->product_id && ! $expense->tool_id && $expense->typeLabel()) {
                $key = 'type:'.$expense->expense_type;
                $rows[$key] ??= array_merge($this->blankRow($expense->typeLabel()), ['key' => $key, 'type' => 'Cost']);
            } else {
                $key = $this->keyFor($expense->product_id, $expense->tool_id, $expense->label);
                $rows[$key] ??= $this->blankRow($expense->label);
            }
            $rows[$key]['expenses'] += (float) $expense->amount;
        }

        // Loan repayments: money paid back out, counted against the software that borrowed it.
        foreach (FinanceLoanRepayment::with('capitalEntry')->get() as $repayment) {
            $entry = $repayment->capitalEntry;
            if (! $entry || ! $in($repayment->paid_at ?? $repayment->created_at)) {
                continue;
            }
            $key = $this->keyFor($entry->product_id, $entry->tool_id, $entry->label);
            $rows[$key] ??= $this->blankRow($entry->label);
            $rows[$key]['repaid'] += (float) $repayment->amount;
        }

        $rows = array_values($rows);

        foreach ($rows as &$row) {
            $row['balance'] = $row['capital'] + $row['income'] - $row['expenses'] - $row['repaid'];
        }

        usort($rows, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $rows;
    }

    public function totals(array $rows): array
    {
        return [
            'capital' => array_sum(array_column($rows, 'capital')),
            'income' => array_sum(array_column($rows, 'income')),
            'expenses' => array_sum(array_column($rows, 'expenses')),
            'repaid' => array_sum(array_column($rows, 'repaid')),
            'balance' => array_sum(array_column($rows, 'balance')),
        ];
    }

    protected function keyFor(?int $productId, ?int $toolId, string $label): string
    {
        if ($productId) {
            return 'product:' . $productId;
        }

        if ($toolId) {
            return 'tool:' . $toolId;
        }

        return 'label:' . strtolower(trim($label));
    }

    protected function blankRow(string $label): array
    {
        return [
            'key' => 'label:' . strtolower(trim($label)),
            'label' => $label,
            'type' => 'Other',
            'capital' => 0,
            'income' => 0,
            'expenses' => 0,
            'repaid' => 0,
        ];
    }
}
