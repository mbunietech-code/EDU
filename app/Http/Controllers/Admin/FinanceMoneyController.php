<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceMoneyAccount;
use App\Services\Finance\FinanceException;
use App\Services\Finance\MoneyAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Finance → Money accounts ("mifuko"): where the money is, each account's
 * statement, transfers, and tying earlier records to an account.
 *
 * Accounts are looked up by id inside each action (not route model binding),
 * so the pages answer with a notice instead of an error before the 0034
 * database change is applied.
 */
class FinanceMoneyController extends Controller
{
    public function __construct(private MoneyAccountService $money)
    {
    }

    public function index()
    {
        if (! MoneyAccountService::ready()) {
            return view('admin.finance.money.index', ['ready' => false]);
        }

        $balances = $this->money->balances();
        $unassigned = $this->money->unassigned();

        return view('admin.finance.money.index', [
            'ready' => true,
            'balances' => $balances,
            'total' => (float) $balances->where('account.is_active', true)->sum('balance'),
            'unassigned' => $unassigned,
            'accounts' => $this->money->options(),
            'methodChoices' => $this->money->methodChoices(),
            'methodMap' => $this->money->methodMap(),
            'types' => FinanceMoneyAccount::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        if ($redirect = $this->notReady()) {
            return $redirect;
        }

        try {
            $account = $this->money->save($request->validate($this->money->accountRules()), null, auth()->id());
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.finance.money.show', $account)->with('success', "{$account->name} added.");
    }

    public function show(int $moneyAccount)
    {
        if ($redirect = $this->notReady()) {
            return $redirect;
        }
        $moneyAccount = FinanceMoneyAccount::findOrFail($moneyAccount);

        $rows = $this->money->statement($moneyAccount);

        return view('admin.finance.money.show', [
            'account' => $moneyAccount,
            'rows' => $rows,
            'balance' => round((float) $moneyAccount->opening_balance + (float) $rows->sum('amount'), 2),
            'methodLabels' => collect($moneyAccount->methodCodes())->map(fn ($c) => $this->money->methodLabel($c))->all(),
        ]);
    }

    public function edit(int $moneyAccount)
    {
        if ($redirect = $this->notReady()) {
            return $redirect;
        }
        $moneyAccount = FinanceMoneyAccount::findOrFail($moneyAccount);

        return view('admin.finance.money.edit', [
            'account' => $moneyAccount,
            'methodChoices' => $this->money->methodChoices(),
            'methodMap' => $this->money->methodMap(),
            'types' => FinanceMoneyAccount::TYPES,
        ]);
    }

    public function update(Request $request, int $moneyAccount)
    {
        if ($redirect = $this->notReady()) {
            return $redirect;
        }
        $moneyAccount = FinanceMoneyAccount::findOrFail($moneyAccount);

        try {
            $this->money->save($request->validate($this->money->accountRules($moneyAccount)), $moneyAccount, auth()->id());
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.finance.money.show', $moneyAccount)->with('success', 'Money account updated.');
    }

    public function transfer(Request $request)
    {
        if ($redirect = $this->notReady()) {
            return $redirect;
        }

        $this->money->transfer($request->validate($this->money->transferRules()), auth()->id());

        return back()->with('success', 'Transfer recorded.');
    }

    public function assign(Request $request)
    {
        if ($redirect = $this->notReady()) {
            return $redirect;
        }

        $data = $request->validate([
            'money_account_id' => ['required', 'integer', Rule::exists('finance_money_accounts', 'id')],
            'expense' => ['nullable', 'array'],
            'expense.*' => ['integer'],
            'capital' => ['nullable', 'array'],
            'capital.*' => ['integer'],
            'repayment' => ['nullable', 'array'],
            'repayment.*' => ['integer'],
        ]);

        $account = FinanceMoneyAccount::findOrFail($data['money_account_id']);
        $count = $this->money->assign($data, $account);

        return back()->with($count > 0 ? 'success' : 'error', $count > 0
            ? "{$count} ".\Illuminate\Support\Str::plural('record', $count)." now go through {$account->name}."
            : 'Tick at least one record to assign.');
    }

    private function notReady()
    {
        return MoneyAccountService::ready()
            ? null
            : redirect()->route('admin.finance.money.index');
    }
}
