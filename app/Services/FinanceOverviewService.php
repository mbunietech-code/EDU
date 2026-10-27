<?php

namespace App\Services;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tool;
use App\Support\FinanceDates;
use App\Support\FinancePeriod;
use Illuminate\Support\Collection;

class FinanceOverviewService
{
    /**
     * Build a per-software finance summary: capital, income (from real
     * approved payments), expenses, loan repayments and the resulting
     * balance, plus totals. With a period, only what happened in it counts
     * (payments on the day the customer paid, capital on the day it was
     * received, expenses and repayments on their own dates — all on Tanzania
     * time, see FinanceDates) and the balance is the period's net.
     */
    public function rows(?FinancePeriod $period = null): array
    {
        $in = fn ($at) => $period === null || $period->contains($at);

        $rows = [];
        $this->items = [];
        // A record typed with a software's name but not linked to it ("Chatgpt",
        // "Claude Pro") still lands on that software's line.
        $this->names = [];

        foreach (Product::orderBy('name')->get() as $product) {
            $key = 'product:' . $product->id;
            $this->names[$this->norm($product->name)] ??= $key;
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

        // A capital entry (or untyped expense) named after a cost type ("Internet
        // / bundles") lands on that type's line.
        foreach (FinanceExpense::types() as $type => $t) {
            $this->names[$this->norm($t['label'])] ??= 'type:'.$type;
        }

        foreach (Tool::orderBy('name')->get() as $tool) {
            $key = 'tool:' . $tool->id;
            $this->names[$this->norm($tool->name)] ??= $key;
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
        $payments = Payment::where('status', 'approved')
            ->whereHas('order', fn ($q) => $q->where(fn ($w) => $w->whereNotNull('product_id')->orWhereNotNull('tool_id')))
            ->with('order:id,order_number,product_id,tool_id', 'user:id,name')
            ->get();

        foreach ($payments as $payment) {
            $key = $payment->order->product_id ? 'product:' . $payment->order->product_id : 'tool:' . $payment->order->tool_id;
            if (! isset($rows[$key]) || ! $in(FinanceDates::payment($payment))) {
                continue;
            }
            $rows[$key]['income'] += (float) $payment->amount;
            $this->items[$key]['income'][] = $payment;
        }

        // Capital and expenses: manually recorded per software.
        foreach (FinanceCapitalEntry::all() as $entry) {
            if (! $in(FinanceDates::capital($entry))) {
                continue;
            }
            $key = $this->keyFor($entry->product_id, $entry->tool_id, $entry->label);
            $rows[$key] ??= $this->rowFor($key, $entry->label);
            $rows[$key]['capital'] += (float) $entry->amount;
            $this->items[$key]['capital'][] = $entry;
        }

        foreach (FinanceExpense::all() as $expense) {
            if (! $in($expense->spent_at ? FinanceDates::day($expense->spent_at) : FinanceDates::local($expense->created_at))) {
                continue;
            }
            // General costs with a type share one line per type ("Electricity
            // (LUKU)"), whatever label each was typed with.
            if (! $expense->product_id && ! $expense->tool_id && $expense->typeLabel()) {
                $key = 'type:'.$expense->expense_type;
                $rows[$key] ??= $this->rowFor($key, $expense->typeLabel());
            } else {
                $key = $this->keyFor($expense->product_id, $expense->tool_id, $expense->label);
                $rows[$key] ??= $this->rowFor($key, $expense->label);
            }
            $rows[$key]['expenses'] += (float) $expense->amount;
            $this->items[$key]['expenses'][] = $expense;
        }

        // Loan repayments: money paid back out, counted against the software that borrowed it.
        foreach (FinanceLoanRepayment::with('capitalEntry')->get() as $repayment) {
            $entry = $repayment->capitalEntry;
            if (! $entry || ! $in($repayment->paid_at ? FinanceDates::day($repayment->paid_at) : FinanceDates::local($repayment->created_at))) {
                continue;
            }
            $key = $this->keyFor($entry->product_id, $entry->tool_id, $entry->label);
            $rows[$key] ??= $this->rowFor($key, $entry->label);
            $rows[$key]['repaid'] += (float) $repayment->amount;
            $this->items[$key]['repaid'][] = $repayment;
        }

        $rows = array_values($rows);

        foreach ($rows as &$row) {
            $row['balance'] = $row['capital'] + $row['income'] - $row['expenses'] - $row['repaid'];
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $rows;
    }

    /**
     * One line of the overview (a software, a cost type or a typed label) with
     * the records behind each of its numbers, for the same period.
     *
     * @return array{row: array, income: Collection, capital: Collection, expenses: Collection, repaid: Collection}|null
     */
    public function line(string $key, ?FinancePeriod $period = null): ?array
    {
        $row = collect($this->rows($period))->firstWhere('key', $key);
        if (! $row && str_starts_with($key, 'type:') && isset(FinanceExpense::types()[substr($key, 5)])) {
            // A cost type with nothing recorded in this period yet.
            $row = $this->rowFor($key, FinanceExpense::types()[substr($key, 5)]['label']) + ['balance' => 0];
        }
        if (! $row) {
            return null;
        }
        $items = $this->items[$key] ?? [];
        $newest = fn (string $part, callable $date) => collect($items[$part] ?? [])->sortByDesc(fn ($r) => [$date($r)?->toDateString(), $r->id])->values();

        return [
            'row' => $row,
            'income' => $newest('income', fn ($p) => FinanceDates::payment($p)),
            'capital' => $newest('capital', fn ($c) => FinanceDates::capital($c)),
            'expenses' => $newest('expenses', fn ($e) => $e->spent_at),
            'repaid' => $newest('repaid', fn ($r) => $r->paid_at),
        ];
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

        return $this->names[$this->norm($label)] ?? 'label:' . $this->norm($label);
    }

    /** @var array<string, array<string, list<mixed>>> row key => part => records behind it */
    private array $items = [];

    /** @var array<string, string> software name (normalised) => row key */
    private array $names = [];

    private function norm(string $label): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($label)));
    }

    /** A new, empty line for this key (a cost type shows its type's name). */
    private function rowFor(string $key, string $label): array
    {
        if (str_starts_with($key, 'type:')) {
            $type = FinanceExpense::types()[substr($key, 5)] ?? null;

            return array_merge($this->blankRow($type['label'] ?? $label), ['key' => $key, 'type' => 'Cost']);
        }

        return array_merge($this->blankRow($label), ['key' => $key]);
    }

    protected function blankRow(string $label): array
    {
        return [
            'key' => 'label:' . $this->norm($label),
            'label' => $label,
            'type' => 'Other',
            'capital' => 0,
            'income' => 0,
            'expenses' => 0,
            'repaid' => 0,
        ];
    }
}
