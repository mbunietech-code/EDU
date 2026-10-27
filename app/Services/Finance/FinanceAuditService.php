<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\DeletedRecord;
use App\Models\FinanceAllocation;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceMoneyAccount;
use App\Support\FinanceDates;
use Illuminate\Support\Collection;

/**
 * Finance → Audit: what needs a look before the books can be trusted
 * (checks), and who added, changed or removed what (activity).
 */
class FinanceAuditService
{
    /** Finance records whose removal is kept under deleted records. */
    private const ENTITIES = [
        'FinanceExpense' => 'Expense',
        'FinanceCapitalEntry' => 'Capital',
        'FinanceLoanRepayment' => 'Loan repayment',
        'FinanceMoneyTransfer' => 'Transfer',
        'FinanceAllocation' => 'Given from available money',
        'FinanceMoneyAccount' => 'Money account',
        'FinanceStaff' => 'Staff',
        'FinanceStaffContract' => 'Contract',
    ];

    /** @var array<int, string>|null */
    private ?array $accountNames = null;

    public function __construct(private MoneyAccountService $money, private PurchaseExpenseService $purchases)
    {
    }

    /**
     * Each check: title, severity (problem | warning | ok), what it means and
     * the records to open.
     *
     * @return list<array{key: string, title: string, severity: string, help: string, items: list<array{label: string, detail: ?string, url: ?string}>}>
     */
    public function checks(): array
    {
        $checks = [];
        $moneyReady = MoneyAccountService::ready();

        if ($moneyReady) {
            $balances = $this->money->balances(false);

            // 1. A statement that goes below zero: money spent before it came in.
            $items = [];
            foreach ($balances as $b) {
                $rows = $this->money->statement($b['account']);
                $below = $rows->filter(fn ($r) => $r['balance'] < 0);
                if ($below->isEmpty()) {
                    continue;
                }
                $low = $below->sortBy('balance')->first();
                $items[] = [
                    'label' => $b['account']->name,
                    'detail' => 'Below zero from '.$below->first()['date']->format('d M Y').' · lowest TZS '.number_format($low['balance']).' on '.$low['date']->format('d M Y')
                        .($b['balance'] < 0 ? ' · still below zero now' : ''),
                    'url' => route('admin.finance.money.show', $b['account']),
                ];
            }
            $checks[] = $this->check('negative', 'Accounts that go below zero', $items, 'problem',
                'Money was paid out of an account before enough came in. Usually an expense has the wrong “Paid from”, or a transfer has the wrong date.');

            // 2. More given to lines than an account holds.
            $items = [];
            if (FinanceAllocation::ready()) {
                $given = FinanceAllocation::groupBy('money_account_id')->selectRaw('money_account_id, SUM(amount) as total')->pluck('total', 'money_account_id');
                foreach ($balances as $b) {
                    $total = (float) ($given[$b['account']->id] ?? 0);
                    if ($total > $b['balance'] + 0.009) {
                        $items[] = [
                            'label' => $b['account']->name,
                            'detail' => 'TZS '.number_format($total).' given to lines, TZS '.number_format($b['balance']).' in the account',
                            'url' => route('admin.finance.line', ['line' => \App\Services\FinanceOverviewService::POOL]),
                        ];
                    }
                }
            }
            $checks[] = $this->check('given', 'More given than an account holds', $items, 'problem',
                'Money “given from available money” is more than what is left in that account.');

            // 3. Records tied to no account.
            $unassigned = $this->money->unassigned();
            $items = [];
            if ($unassigned['totals']['items'] > 0 || $unassigned['totals']['payments'] != 0) {
                $items[] = [
                    'label' => $unassigned['totals']['items'].' records · TZS '.number_format($unassigned['totals']['net']),
                    'detail' => 'Expenses, capital, repayments or payment methods with no money account',
                    'url' => route('admin.finance.money.index'),
                ];
            }
            $checks[] = $this->check('unassigned', 'Not tied to a money account', $items, 'warning',
                'They are counted in the overview but in no account, so account balances look smaller than the books.');
        }

        // 4. Two expenses with the same amount within three days.
        $items = [];
        $expenses = FinanceExpense::orderBy('spent_at')->get(['id', 'label', 'amount', 'spent_at']);
        foreach ($expenses->groupBy(fn ($e) => (string) round((float) $e->amount, 2)) as $same) {
            $same = $same->values();
            for ($i = 1; $i < $same->count(); $i++) {
                [$a, $b] = [$same[$i - 1], $same[$i]];
                if ($a->spent_at && $b->spent_at && $a->spent_at->diffInDays($b->spent_at) <= 3) {
                    $items[] = [
                        'label' => "{$a->label} and {$b->label} · TZS ".number_format($a->amount),
                        'detail' => $a->spent_at->format('d M').' and '.$b->spent_at->format('d M Y'),
                        'url' => route('admin.finance.expenses.edit', $b),
                    ];
                }
            }
        }
        $checks[] = $this->check('duplicates', 'Possible double entries', $items, 'warning',
            'Same amount, at most three days apart. Leave them if they really are two payments.');

        // 5. Capital without the day it was received.
        $items = [];
        if (FinanceDates::hasReceivedDate()) {
            foreach (FinanceCapitalEntry::whereNull('received_at')->orderBy('created_at')->get() as $c) {
                $items[] = [
                    'label' => "{$c->label} · {$c->source} · TZS ".number_format($c->amount),
                    'detail' => 'Counted on '.FinanceDates::local($c->created_at)?->format('d M Y').', the day it was typed in',
                    'url' => route('admin.finance.capital.edit', $c),
                ];
            }
        }
        $checks[] = $this->check('received', 'Capital without a received date', $items, 'warning',
            'Set the day the money really came in, so periods and statements show it on the right day.');

        // 6. Purchases not in Finance yet.
        $items = [];
        if (FinanceExpense::typesReady()) {
            $pending = $this->purchases->pending('account', $this->purchases->accounts())->count()
                + $this->purchases->pending('asset', $this->purchases->assets())->count();
            if ($pending > 0) {
                $items[] = ['label' => "{$pending} purchases", 'detail' => 'Bought, but no expense for them yet', 'url' => route('admin.finance.purchases.index')];
            }
        }
        $checks[] = $this->check('purchases', 'Purchases not in Finance yet', $items, 'warning',
            'Product accounts or company assets with a cost that no expense covers.');

        // 7. Typed-in expenses without a receipt.
        $items = [];
        if (\Illuminate\Support\Facades\Schema::hasColumn('finance_expenses', 'receipt_path')) {
            $missing = FinanceExpense::whereNull('receipt_path')
                ->when(FinanceExpense::typesReady(), fn ($q) => $q->whereNull('source_type'))
                ->orderByDesc('spent_at')->get(['id', 'label', 'amount', 'spent_at']);
            foreach ($missing as $e) {
                $items[] = ['label' => "{$e->label} · TZS ".number_format($e->amount), 'detail' => $e->spent_at?->format('d M Y'), 'url' => route('admin.finance.expenses.edit', $e)];
            }
        }
        $checks[] = $this->check('receipts', 'Expenses without a receipt', $items, 'warning',
            'Typed-in expenses with no picture or PDF of the receipt. Not wrong, but harder to prove later.');

        return $checks;
    }

    /**
     * Who added, changed or removed what in Finance, newest first.
     *
     * @return Collection<int, array{at: \Illuminate\Support\Carbon, who: ?string, kind: string, what: string, detail: ?string}>
     */
    public function activity(int $limit = 150): Collection
    {
        $logs = ActivityLog::with('actor:id,name')
            ->where('action', 'like', 'finance_%')
            ->whereNotIn('action', ['finance_unlocked'])
            ->latest('id')->limit($limit)->get()
            ->map(fn (ActivityLog $l) => [
                'at' => FinanceDates::local($l->created_at),
                'who' => $l->actor?->name,
                'kind' => $this->kindOf($l->action),
                'what' => $this->actionLabel($l->action),
                'detail' => $this->summary((array) $l->metadata),
            ]);

        $removed = DeletedRecord::with('deleter:id,name')
            ->whereIn('entity', array_keys(self::ENTITIES))
            ->latest('id')->limit($limit)->get()
            ->map(fn (DeletedRecord $d) => [
                'at' => FinanceDates::local($d->created_at),
                'who' => $d->deleter?->name,
                'kind' => 'removed',
                'what' => self::ENTITIES[$d->entity].' removed: '.$d->label,
                'detail' => trim(collect([
                    isset($d->snapshot['amount']) ? 'TZS '.number_format((float) $d->snapshot['amount']) : null,
                    $d->reason ? 'Reason: '.$d->reason : null,
                ])->filter()->implode(' · ')) ?: null,
            ]);

        return $logs->concat($removed)->sortByDesc(fn ($r) => $r['at']?->timestamp ?? 0)->take($limit)->values();
    }

    private function check(string $key, string $title, array $items, string $severity, string $help): array
    {
        return ['key' => $key, 'title' => $title, 'severity' => $items === [] ? 'ok' : $severity, 'help' => $help, 'items' => $items];
    }

    private function kindOf(string $action): string
    {
        return match (true) {
            str_contains($action, 'removed') || str_contains($action, 'reopened') => 'removed',
            str_contains($action, 'updated') || str_contains($action, 'changed') || str_contains($action, 'linked') || str_contains($action, 'assigned') => 'changed',
            default => 'added',
        };
    }

    private function actionLabel(string $action): string
    {
        return [
            'finance_expense_added' => 'Expense added',
            'finance_expense_updated' => 'Expense changed',
            'finance_expense_auto' => 'Expense recorded automatically',
            'finance_expense_auto_removed' => 'Automatic expense removed',
            'finance_expense_linked' => 'Purchase linked to an expense',
            'finance_capital_added' => 'Capital added',
            'finance_capital_updated' => 'Capital changed',
            'finance_loan_repaid' => 'Loan repayment added',
            'finance_money_transferred' => 'Transfer added',
            'finance_money_transfer_updated' => 'Transfer changed',
            'finance_money_assigned' => 'Records assigned to an account',
            'finance_money_account_created' => 'Money account added',
            'finance_money_account_updated' => 'Money account changed',
            'finance_allocation_added' => 'Given from available money',
            'finance_pin_changed' => 'Finance PIN changed',
            'finance_pin_change_failed' => 'Finance PIN change failed',
        ][$action] ?? ucfirst(str_replace('_', ' ', substr($action, 8)));
    }

    private function summary(array $meta): ?string
    {
        $parts = [];
        foreach (['label', 'source', 'name'] as $k) {
            if (! empty($meta[$k]) && is_scalar($meta[$k])) {
                $parts[] = (string) $meta[$k];
            }
        }
        if (isset($meta['amount']) && is_numeric($meta['amount'])) {
            $parts[] = 'TZS '.number_format((float) $meta['amount']);
        }
        foreach (['from' => 'from', 'to' => 'to'] as $k => $word) {
            if (isset($meta[$k]) && is_numeric($meta[$k])) {
                $this->accountNames ??= FinanceMoneyAccount::pluck('name', 'id')->all();
                $parts[] = $word.' '.($this->accountNames[(int) $meta[$k]] ?? '#'.$meta[$k]);
            }
        }
        if (isset($meta['changes']) && is_array($meta['changes'])) {
            foreach ($meta['changes'] as $field => [$old, $new]) {
                $parts[] = str_replace('_', ' ', $field).': '.$old.' → '.$new;
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
