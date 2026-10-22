<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceMoneyTransfer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Money accounts ("mifuko"): balance of each, its statement (a cash book with
 * a running balance), transfers between them, and the money not assigned to
 * any of them yet. Shared by the web Finance pages and the app API.
 *
 * Balance = opening balance
 *         + approved customer payments whose method code maps to the account
 *         + capital received into it
 *         − expenses and loan repayments paid from it
 *         ± transfers.
 * Movements dated before the opening date are left out: the opening balance
 * already includes them.
 */
class MoneyAccountService
{
    private static ?bool $ready = null;

    /** The 0034 database change is applied (tables and columns exist). */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('finance_money_accounts')
                    && Schema::hasTable('finance_money_transfers')
                    && Schema::hasColumn('finance_expenses', 'money_account_id')
                    && Schema::hasColumn('finance_capital_entries', 'money_account_id')
                    && Schema::hasColumn('finance_loan_repayments', 'money_account_id');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public static function forgetReady(): void
    {
        self::$ready = null;
    }

    /** Optional "which money account" rule for expense / capital / repayment forms. */
    public static function fieldRules(): array
    {
        return self::ready()
            ? ['money_account_id' => ['nullable', 'integer', Rule::exists('finance_money_accounts', 'id')]]
            : [];
    }

    /** Active accounts for form dropdowns (empty before the database change). */
    public function options(): Collection
    {
        return self::ready()
            ? FinanceMoneyAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type'])
            : collect();
    }

    // ------------------------------------------------------------------
    // Accounts and transfers
    // ------------------------------------------------------------------

    public function accountRules(?FinanceMoneyAccount $account = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(FinanceMoneyAccount::TYPES))],
            'number' => ['nullable', 'string', 'max:120'],
            'opening_balance' => ['nullable', 'numeric', 'min:-9999999999', 'max:9999999999'],
            'opening_date' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @throws FinanceException when a payment method already lands in another account */
    public function save(array $data, ?FinanceMoneyAccount $account, ?int $userId): FinanceMoneyAccount
    {
        $codes = array_values(array_unique(array_filter($data['payment_methods'] ?? [])));
        $taken = $this->methodMap();
        foreach ($codes as $code) {
            if (isset($taken[$code]) && $taken[$code]->id !== $account?->id) {
                throw new FinanceException('"'.$this->methodLabel($code).'" payments already land in '.$taken[$code]->name.'. Remove it there first.');
            }
        }

        $data['payment_methods'] = $codes;
        $data['opening_balance'] = (float) ($data['opening_balance'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        if ($account) {
            $account->update($data);
            ActivityLog::log('finance_money_account_updated', 'FinanceMoneyAccount', $account->id, ['name' => $account->name]);

            return $account;
        }

        $account = FinanceMoneyAccount::create($data + ['created_by' => $userId]);
        ActivityLog::log('finance_money_account_created', 'FinanceMoneyAccount', $account->id, ['name' => $account->name]);

        return $account;
    }

    public function transferRules(): array
    {
        return [
            'from_account_id' => ['required', 'integer', Rule::exists('finance_money_accounts', 'id')],
            'to_account_id' => ['required', 'integer', 'different:from_account_id', Rule::exists('finance_money_accounts', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'transferred_at' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function transfer(array $data, ?int $userId): FinanceMoneyTransfer
    {
        $transfer = FinanceMoneyTransfer::create($data + ['created_by' => $userId]);
        ActivityLog::log('finance_money_transferred', 'FinanceMoneyTransfer', $transfer->id, [
            'from' => $transfer->from_account_id,
            'to' => $transfer->to_account_id,
            'amount' => $transfer->amount,
        ]);

        return $transfer;
    }

    // ------------------------------------------------------------------
    // Payment method codes
    // ------------------------------------------------------------------

    /** @return array<string, FinanceMoneyAccount> code => account it lands in */
    public function methodMap(): array
    {
        $map = [];
        foreach (FinanceMoneyAccount::all() as $account) {
            foreach ($account->methodCodes() as $code) {
                $map[$code] = $account;
            }
        }

        return $map;
    }

    /** @return array<string, string> every payment method code customers can use or have used => label */
    public function methodChoices(): array
    {
        $choices = PaymentMethod::orderBy('sort_order')->pluck('name', 'code')->all();
        foreach (array_keys((array) config('payments.gateways', [])) as $code) {
            $choices[$code] ??= $this->methodLabel($code);
        }
        foreach (Payment::query()->distinct()->pluck('payment_method') as $code) {
            if ($code !== null && $code !== '') {
                $choices[$code] ??= $this->methodLabel($code);
            }
        }

        return $choices;
    }

    public function methodLabel(string $code): string
    {
        static $names = null;
        $names ??= PaymentMethod::pluck('name', 'code')->all();

        return $names[$code] ?? config("payments.gateways.{$code}.label") ?? ucwords(str_replace('_', ' ', $code));
    }

    // ------------------------------------------------------------------
    // Balances and statements
    // ------------------------------------------------------------------

    /**
     * Every movement of one account, oldest first, with a running balance.
     *
     * @return Collection<int, array{date: Carbon, kind: string, label: string, detail: ?string, ref: ?string, amount: float, balance: float, url: ?string}>
     */
    public function statement(FinanceMoneyAccount $account): Collection
    {
        $from = $account->opening_date?->copy()->startOfDay();
        $rows = collect();

        $codes = $account->methodCodes();
        if ($codes !== []) {
            Payment::where('status', 'approved')->whereIn('payment_method', $codes)
                ->with('order:id,order_number,product_id,tool_id', 'order.product:id,name', 'order.tool:id,name')
                ->get()
                ->each(function (Payment $p) use ($rows) {
                    $rows->push($this->row($p->reviewed_at ?? $p->created_at, 'income', 'Sale: '.($p->order?->itemName() ?? 'Order'),
                        $p->order?->order_number, $p->transaction_reference, (float) $p->amount, route('admin.payments.show', $p), 1, $p->id));
                });
        }

        $account->capitalEntries()->get()->each(function (FinanceCapitalEntry $c) use ($rows) {
            $rows->push($this->row($c->created_at, $c->is_loan ? 'loan' : 'capital', ($c->is_loan ? 'Loan: ' : 'Capital: ').$c->label,
                $c->source, null, (float) $c->amount, route('admin.finance.capital.edit', $c), 2, $c->id));
        });

        $account->transfersIn()->with('fromAccount:id,name')->get()->each(function (FinanceMoneyTransfer $t) use ($rows) {
            $rows->push($this->row($t->transferred_at, 'transfer_in', 'From '.($t->fromAccount?->name ?? 'account'),
                $t->notes, $t->reference, (float) $t->amount, null, 3, $t->id));
        });

        $account->expenses()->get()->each(function (FinanceExpense $e) use ($rows) {
            $rows->push($this->row($e->spent_at, 'expense', $e->label, $e->category, null, -(float) $e->amount,
                route('admin.finance.expenses.edit', $e), 4, $e->id));
        });

        $account->loanRepayments()->with('capitalEntry:id,label,source')->get()->each(function (FinanceLoanRepayment $r) use ($rows) {
            $rows->push($this->row($r->paid_at, 'repayment', 'Loan repaid: '.($r->capitalEntry?->label ?? 'loan'),
                $r->capitalEntry?->source, $r->reference, -(float) $r->amount,
                $r->capitalEntry ? route('admin.finance.capital.repayments.index', $r->capitalEntry) : null, 5, $r->id));
        });

        $account->transfersOut()->with('toAccount:id,name')->get()->each(function (FinanceMoneyTransfer $t) use ($rows) {
            $rows->push($this->row($t->transferred_at, 'transfer_out', 'To '.($t->toAccount?->name ?? 'account'),
                $t->notes, $t->reference, -(float) $t->amount, null, 6, $t->id));
        });

        $balance = (float) $account->opening_balance;

        return $rows
            ->filter(fn ($r) => $from === null || $r['date']->gte($from))
            ->sortBy('sort')
            ->values()
            ->map(function ($r) use (&$balance) {
                $balance = round($balance + $r['amount'], 2);
                unset($r['sort']);

                return $r + ['balance' => $balance];
            });
    }

    /**
     * All accounts with their balance, money in and money out.
     *
     * @return Collection<int, array{account: FinanceMoneyAccount, in: float, out: float, balance: float}>
     */
    public function balances(bool $includeArchived = true): Collection
    {
        return FinanceMoneyAccount::query()
            ->when(! $includeArchived, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')->orderBy('name')
            ->get()
            ->map(function (FinanceMoneyAccount $account) {
                $rows = $this->statement($account);

                return [
                    'account' => $account,
                    'in' => (float) $rows->where('amount', '>', 0)->sum('amount'),
                    'out' => (float) -$rows->where('amount', '<', 0)->sum('amount'),
                    'balance' => round((float) $account->opening_balance + (float) $rows->sum('amount'), 2),
                ];
            });
    }

    /**
     * Money recorded in Finance but not tied to any account yet.
     *
     * @return array{payments: Collection, expenses: Collection, capital: Collection, repayments: Collection, totals: array}
     */
    public function unassigned(): array
    {
        $mapped = array_keys($this->methodMap());

        $payments = Payment::where('status', 'approved')
            ->when($mapped !== [], fn ($q) => $q->whereNotIn('payment_method', $mapped))
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($r) => ['code' => $r->payment_method, 'label' => $this->methodLabel((string) $r->payment_method), 'count' => (int) $r->count, 'total' => (float) $r->total]);

        $expenses = FinanceExpense::whereNull('money_account_id')->orderBy('spent_at')->orderBy('id')->get();
        $capital = FinanceCapitalEntry::whereNull('money_account_id')->orderBy('created_at')->orderBy('id')->get();
        $repayments = FinanceLoanRepayment::whereNull('money_account_id')->with('capitalEntry:id,label,source')->orderBy('paid_at')->orderBy('id')->get();

        $totals = [
            'payments' => (float) $payments->sum('total'),
            'capital' => (float) $capital->sum('amount'),
            'expenses' => (float) $expenses->sum('amount'),
            'repayments' => (float) $repayments->sum('amount'),
        ];
        $totals['net'] = $totals['payments'] + $totals['capital'] - $totals['expenses'] - $totals['repayments'];
        $totals['items'] = $expenses->count() + $capital->count() + $repayments->count();

        return compact('payments', 'expenses', 'capital', 'repayments', 'totals');
    }

    public const ASSIGNABLE = [
        'expense' => FinanceExpense::class,
        'capital' => FinanceCapitalEntry::class,
        'repayment' => FinanceLoanRepayment::class,
    ];

    /**
     * Tie earlier records to an account. Only money_account_id changes, and
     * only on rows that have none yet, so nothing already assigned moves.
     *
     * @param  array<string, list<int>>  $ids  kind => ids
     */
    public function assign(array $ids, FinanceMoneyAccount $account): int
    {
        $count = 0;

        DB::transaction(function () use ($ids, $account, &$count) {
            foreach (self::ASSIGNABLE as $kind => $model) {
                $list = array_values(array_unique(array_map('intval', $ids[$kind] ?? [])));
                if ($list !== []) {
                    $count += $model::whereIn('id', $list)->whereNull('money_account_id')->update(['money_account_id' => $account->id]);
                }
            }
        });

        ActivityLog::log('finance_money_assigned', 'FinanceMoneyAccount', $account->id, ['records' => $count, 'ids' => $ids]);

        return $count;
    }

    private function row($date, string $kind, string $label, ?string $detail, ?string $ref, float $amount, ?string $url, int $order, int $id): array
    {
        $date = Carbon::parse($date ?? now());

        return [
            'date' => $date,
            'kind' => $kind,
            'label' => $label,
            'detail' => $detail,
            'ref' => $ref,
            'amount' => round($amount, 2),
            'url' => $url,
            'sort' => $date->format('Y-m-d').sprintf('|%d|%010d', $order, $id),
        ];
    }
}
