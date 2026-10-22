<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\FinanceAccess;
use App\Http\Controllers\Controller;
use App\Models\FinanceExpense;
use App\Models\FinanceMoneyAccount;
use App\Services\Finance\FinanceException;
use App\Services\Finance\MoneyAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Money accounts ("mifuko") for the app: balances, statement, add / edit,
 * transfers and assigning earlier records. Same service as the web pages.
 */
class FinanceMoneyController extends Controller
{
    use FinanceAccess;

    public function __construct(private MoneyAccountService $money)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->finance($request);

        if (! MoneyAccountService::ready()) {
            return response()->json(['data' => ['ready' => false, 'accounts' => [], 'unassigned' => null, 'total' => 0],
                'message' => 'Apply database change 0034 from Admin → Database to use money accounts.']);
        }

        $balances = $this->money->balances();
        $u = $this->money->unassigned();

        return response()->json(['data' => [
            'ready' => true,
            'total' => (float) $balances->where('account.is_active', true)->sum('balance'),
            'accounts' => $balances->map(fn ($b) => $this->row($b['account']) + [
                'money_in' => $b['in'],
                'money_out' => $b['out'],
                'balance' => $b['balance'],
                'balance_label' => 'TZS '.number_format($b['balance']),
            ])->values()->all(),
            'unassigned' => [
                'net' => $u['totals']['net'],
                'payments' => $u['payments']->values()->all(),
                'expenses' => $u['expenses']->map(fn ($e) => ['id' => $e->id, 'date' => optional($e->spent_at)->toDateString(), 'label' => $e->label, 'amount' => (float) $e->amount])->values()->all(),
                'capital' => $u['capital']->map(fn ($c) => ['id' => $c->id, 'date' => optional($c->created_at)->toDateString(), 'label' => $c->label, 'source' => $c->source, 'is_loan' => (bool) $c->is_loan, 'amount' => (float) $c->amount])->values()->all(),
                'repayments' => $u['repayments']->map(fn ($r) => ['id' => $r->id, 'date' => optional($r->paid_at)->toDateString(), 'label' => $r->capitalEntry?->label, 'amount' => (float) $r->amount])->values()->all(),
            ],
            'types' => FinanceMoneyAccount::TYPES,
            'payment_methods' => $this->money->methodChoices(),
        ]]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        $account = FinanceMoneyAccount::findOrFail($id);
        $rows = $this->money->statement($account);

        return response()->json(['data' => $this->row($account) + [
            'balance' => round((float) $account->opening_balance + (float) $rows->sum('amount'), 2),
            'statement' => $rows->map(fn ($r) => [
                'date' => $r['date']->toDateString(),
                'kind' => $r['kind'],
                'label' => $r['label'],
                'detail' => $r['detail'],
                'ref' => $r['ref'],
                'amount' => $r['amount'],
                'balance' => $r['balance'],
            ])->values()->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        try {
            $account = $this->money->save($request->validate($this->money->accountRules()), null, $request->user()->id);
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->row($account), 'message' => 'Money account added.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        $account = FinanceMoneyAccount::findOrFail($id);
        try {
            $this->money->save($request->validate($this->money->accountRules($account)), $account, $request->user()->id);
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->row($account->fresh()), 'message' => 'Money account updated.']);
    }

    public function transfer(Request $request): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        $transfer = $this->money->transfer($request->validate($this->money->transferRules()), $request->user()->id);

        return response()->json(['data' => ['id' => $transfer->id], 'message' => 'Transfer recorded.'], 201);
    }

    public function assign(Request $request): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        $data = $request->validate([
            'money_account_id' => ['required', 'integer', Rule::exists('finance_money_accounts', 'id')],
            'expense' => ['nullable', 'array'], 'expense.*' => ['integer'],
            'capital' => ['nullable', 'array'], 'capital.*' => ['integer'],
            'repayment' => ['nullable', 'array'], 'repayment.*' => ['integer'],
        ]);
        $count = $this->money->assign($data, FinanceMoneyAccount::findOrFail($data['money_account_id']));

        return response()->json(['data' => ['assigned' => $count], 'message' => "{$count} assigned."]);
    }

    /** Journal (Dr / Cr per movement) and trial balance; optional ?month=YYYY-MM. */
    public function ledger(Request $request, \App\Services\Finance\LedgerService $ledger): JsonResponse
    {
        $this->finance($request);

        $month = (string) $request->query('month', '');
        $from = $to = null;
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $from = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
            $to = $from->copy()->endOfMonth();
        }
        $journal = $ledger->journal($from, $to);

        return response()->json(['data' => [
            'month' => $from ? $month : null,
            'trial_balance' => $ledger->trialBalance($journal)->all(),
            'journal' => $journal->map(fn ($l) => [
                'date' => $l['date']->toDateString(),
                'kind' => $l['kind'],
                'description' => $l['description'],
                'ref' => $l['ref'],
                'dr' => $l['dr'],
                'cr' => $l['cr'],
                'amount' => $l['amount'],
            ])->all(),
        ]]);
    }

    /** Monthly budget against spending; optional ?month=YYYY-MM (default newest month). */
    public function budget(Request $request, \App\Services\Finance\BudgetService $budget): JsonResponse
    {
        $this->finance($request);

        $months = $budget->months();
        $month = (string) $request->query('month', $months[0] ?? now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        return response()->json(['data' => $budget->month(\Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)) + [
            'months' => $months,
            'capital' => $budget->capitalShares(),
        ]]);
    }

    /** Save bucket percentages ({percents: {cogs: 35, …}}); they must add up to 100. */
    public function updateBudget(Request $request, \App\Services\Finance\BudgetService $budget): JsonResponse
    {
        $this->finance($request);
        $data = $request->validate($budget->percentRules());

        try {
            $budget->savePercents($data['percents']);
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Budget percentages saved.']);
    }

    /** Product accounts and company assets with a cost, and which months are recorded as expenses. */
    public function purchases(Request $request, \App\Services\Finance\PurchaseExpenseService $purchases): JsonResponse
    {
        $this->finance($request);

        $map = function (string $kind, $items) use ($purchases) {
            $recorded = $purchases->recorded($kind);

            return $items->map(fn ($i) => [
                'kind' => $kind,
                'id' => $i->id,
                'name' => $i->name,
                'cost' => (float) $i->cost,
                'currency' => $kind === 'account' ? ($i->cost_currency ?: 'TZS') : ($i->currency ?: 'TZS'),
                'cost_tzs' => $purchases->costInTzs((float) $i->cost, $kind === 'account' ? $i->cost_currency : $i->currency),
                'default_type' => $purchases->defaultType($kind, $i),
                'recorded' => collect($recorded[$i->id] ?? [])->map(fn ($e) => [
                    'expense_id' => $e->id, 'month' => $e->spent_at->format('Y-m'), 'amount' => (float) $e->amount,
                ])->values()->all(),
            ])->values()->all();
        };

        return response()->json(['data' => [
            'ready' => FinanceExpense::typesReady(),
            'accounts' => $map('account', $purchases->accounts()),
            'assets' => $map('asset', $purchases->assets()),
            'linkable' => $purchases->linkable()->map(fn ($e) => [
                'id' => $e->id, 'label' => $e->label, 'amount' => (float) $e->amount, 'date' => $e->spent_at->toDateString(),
            ])->values()->all(),
        ]]);
    }

    /** Record one payment of an account / asset as an expense, or link it ({expense_id}) to one already typed in. */
    public function purchase(Request $request, string $kind, int $id, string $action, \App\Services\Finance\PurchaseExpenseService $purchases): JsonResponse
    {
        $this->finance($request);
        abort_unless(in_array($kind, \App\Services\Finance\PurchaseExpenseService::KINDS, true) && in_array($action, ['record', 'link'], true), 404);
        $item = $purchases->find($kind, $id);

        try {
            if ($action === 'record') {
                $expense = $purchases->record($kind, $item, $request->validate($purchases->recordRules()), $request->user()->id);

                return response()->json(['data' => ['expense_id' => $expense->id], 'message' => 'Recorded under Expenses.'], 201);
            }

            $data = $request->validate(['expense_id' => ['required', 'integer', Rule::exists('finance_expenses', 'id')]]);
            $purchases->link($kind, $item, FinanceExpense::findOrFail($data['expense_id']));
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Linked; it will not be counted twice.']);
    }

    /** Profit and loss, profit per product and the month-end checklist; optional ?month=YYYY-MM. */
    public function report(Request $request, \App\Services\Finance\MonthlyReportService $reports): JsonResponse
    {
        $this->finance($request);

        $months = $reports->months();
        $m = (string) $request->query('month', $months[0] ?? now()->format('Y-m'));
        $month = \Illuminate\Support\Carbon::createFromFormat('!Y-m', preg_match('/^\d{4}-\d{2}$/', $m) ? $m : now()->format('Y-m'));

        return response()->json(['data' => [
            'months' => $months,
            'pnl' => $reports->pnl($month),
            'checklist' => collect($reports->checklist($month))->map(fn ($c) => [
                'label' => $c['label'], 'done' => $c['done'], 'detail' => $c['detail'],
            ])->all(),
        ]]);
    }

    private function row(FinanceMoneyAccount $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'type_label' => $a->typeLabel(),
            'number' => $a->number,
            'opening_balance' => (float) $a->opening_balance,
            'opening_date' => optional($a->opening_date)->toDateString(),
            'payment_methods' => $a->methodCodes(),
            'is_active' => (bool) $a->is_active,
            'notes' => $a->notes,
        ];
    }
}
