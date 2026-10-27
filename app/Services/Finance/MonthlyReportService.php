<?php

namespace App\Services\Finance;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinancePayrollPeriod;
use App\Models\FinanceStatutoryReturn;
use App\Models\Payment;
use App\Support\FinanceDates;
use Illuminate\Support\Carbon;

/**
 * Monthly report (Mwongozo wa Fedha §8): profit and loss for a month, profit
 * per product, cash that moved outside profit (capital, loans, repayments),
 * and the month-end checklist with each item's real state.
 */
class MonthlyReportService
{
    public function __construct(private BudgetService $budget)
    {
    }

    public function months(): array
    {
        return $this->budget->months();
    }

    /** @return array{month: string, income: array, cogs: array, gross: float, opex: array, net: float, cash: array, products: array} */
    public function pnl(Carbon $month): array
    {
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

        $income = Payment::where('status', 'approved')
            ->with('order:id,product_id,tool_id', 'order.product:id,name', 'order.tool:id,name')
            ->get()
            ->filter(fn ($p) => FinanceDates::payment($p)?->format('Y-m') === $from->format('Y-m'))
            ->groupBy(fn ($p) => $p->order?->itemName() ?? 'Other')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))
            ->sortDesc()
            ->all();

        $cogs = [];
        $opex = [];
        $expenses = FinanceExpense::with('product:id,name', 'tool:id,name')
            ->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])->get();
        foreach ($expenses as $e) {
            $isCogs = ($e->expense_type ?? null) === 'product_accounts' || $e->category === 'Cost of Goods Sold (COGS)';
            if ($isCogs) {
                $key = $e->product->name ?? $e->tool->name ?? $e->label;
                $cogs[$key] = round(($cogs[$key] ?? 0) + (float) $e->amount, 2);
            } else {
                $key = $e->typeLabel() ?? $e->category ?? 'Uncategorised';
                $opex[$key] = round(($opex[$key] ?? 0) + (float) $e->amount, 2);
            }
        }
        arsort($cogs);
        arsort($opex);

        $incomeTotal = array_sum($income);
        $cogsTotal = array_sum($cogs);
        $gross = round($incomeTotal - $cogsTotal, 2);
        $net = round($gross - array_sum($opex), 2);

        $products = [];
        foreach (array_unique(array_merge(array_keys($income), array_keys($cogs))) as $name) {
            $products[$name] = ['income' => $income[$name] ?? 0.0, 'cogs' => $cogs[$name] ?? 0.0];
            $products[$name]['profit'] = round($products[$name]['income'] - $products[$name]['cogs'], 2);
        }
        uasort($products, fn ($a, $b) => $b['profit'] <=> $a['profit']);

        $capital = FinanceCapitalEntry::all()->filter(fn ($c) => FinanceDates::capital($c)?->format('Y-m') === $from->format('Y-m'))->values();

        return [
            'month' => $from->format('Y-m'),
            'income' => $income,
            'income_total' => round($incomeTotal, 2),
            'cogs' => $cogs,
            'cogs_total' => round($cogsTotal, 2),
            'gross' => $gross,
            'opex' => $opex,
            'opex_total' => round(array_sum($opex), 2),
            'net' => $net,
            'cash' => [
                'capital_in' => round((float) $capital->where('is_loan', false)->sum('amount'), 2),
                'loans_in' => round((float) $capital->where('is_loan', true)->sum('amount'), 2),
                'repaid' => round((float) FinanceLoanRepayment::whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])->sum('amount'), 2),
            ],
            'products' => $products,
        ];
    }

    /**
     * Month-end checklist with each item's state from the system.
     *
     * @return list<array{label: string, done: ?bool, detail: string, route: ?string}>
     */
    public function checklist(Carbon $month): array
    {
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $items = [];

        $pending = Payment::where('status', 'pending')->count();
        $items[] = ['label' => 'Approve customer payments waiting for review', 'done' => $pending === 0,
            'detail' => $pending === 0 ? 'None waiting.' : "{$pending} waiting.", 'route' => route('admin.payments.index')];

        $untyped = FinanceExpense::typesReady()
            ? FinanceExpense::whereNull('expense_type')->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])->count()
            : null;
        $items[] = ['label' => 'Give every expense a type', 'done' => $untyped === null ? null : $untyped === 0,
            'detail' => $untyped === null ? 'Apply database change 0035 first.' : ($untyped === 0 ? 'All typed.' : "{$untyped} without a type this month."),
            'route' => route('admin.finance.expenses.index', ['type' => 'none'])];

        if (MoneyAccountService::ready()) {
            $u = app(MoneyAccountService::class)->unassigned();
            $open = $u['totals']['items'] + $u['payments']->count();
            $items[] = ['label' => 'Every record sits in a money account', 'done' => $open === 0,
                'detail' => $open === 0 ? 'All assigned.' : "{$open} still not assigned.", 'route' => route('admin.finance.money.index')];
        }

        $items[] = ['label' => 'Check each money account against its bank / wallet statement', 'done' => null,
            'detail' => 'Open each statement and type the balance shown by the bank or wallet.', 'route' => route('admin.finance.money.index')];

        $payroll = FinancePayrollPeriod::whereDate('period_month', $from->toDateString())->first();
        $items[] = ['label' => 'Prepare, approve and pay this month\'s payroll', 'done' => $payroll ? $payroll->status === 'paid' : false,
            'detail' => $payroll ? ucfirst($payroll->status).' · net TZS '.number_format((float) $payroll->net_pay) : 'Not prepared yet.',
            'route' => route('admin.finance.payroll.index')];

        $returns = FinanceStatutoryReturn::where('status', '!=', 'paid')->whereDate('due_date', '<=', $to->copy()->addMonth()->endOfMonth()->toDateString())->get();
        $items[] = ['label' => 'Pay PAYE and SDL by the 7th, NSSF and WCF by month end', 'done' => $returns->isEmpty(),
            'detail' => $returns->isEmpty() ? 'Nothing owed.' : $returns->count().' unpaid · TZS '.number_format((float) $returns->sum('amount')),
            'route' => route('admin.finance.returns.index')];

        if (FinanceExpense::typesReady()) {
            $purchases = app(PurchaseExpenseService::class);
            $monthly = $purchases->assets()->where('billing_cycle', 'monthly')->merge($purchases->accounts());
            $recordedKeys = FinanceExpense::where('source_type', 'like', '%:'.$from->format('Y-m'))->get(['source_type', 'source_id'])
                ->map(fn ($e) => strtok($e->source_type, ':').'#'.$e->source_id)->all();
            $missing = $monthly->filter(function ($i) use ($recordedKeys) {
                $kind = $i instanceof \App\Models\Account ? 'account' : 'asset';

                return ! in_array($kind.'#'.$i->id, $recordedKeys, true);
            })->count();
            $items[] = ['label' => 'Record this month\'s purchases (accounts, monthly assets)', 'done' => $missing === 0,
                'detail' => $missing === 0 ? 'All recorded.' : "{$missing} not recorded for this month.", 'route' => route('admin.finance.purchases.index')];
        }

        $over = collect($this->budget->month($from)['rows'])->filter(fn ($r) => $r['left'] < 0)->count();
        $items[] = ['label' => 'Stay within the budget percentages', 'done' => $over === 0,
            'detail' => $over === 0 ? 'Every bucket within budget.' : "{$over} over budget.", 'route' => route('admin.finance.budget.index', ['month' => $from->format('Y-m')])];

        return $items;
    }
}
