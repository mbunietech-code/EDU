<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceExpense;
use App\Services\Finance\FinanceException;
use App\Services\Finance\MoneyAccountService;
use App\Services\Finance\PurchaseExpenseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Finance → Purchases: product accounts and company assets recorded as expenses. */
class FinancePurchaseController extends Controller
{
    public function __construct(private PurchaseExpenseService $purchases)
    {
    }

    public function index(MoneyAccountService $money)
    {
        return view('admin.finance.purchases', [
            'ready' => FinanceExpense::typesReady(),
            'accounts' => $this->purchases->accounts(),
            'assets' => $this->purchases->assets(),
            'recorded' => ['account' => $this->purchases->recorded('account'), 'asset' => $this->purchases->recorded('asset')],
            'linkable' => $this->purchases->linkable(),
            'moneyAccounts' => $money->options(),
            'purchases' => $this->purchases,
        ]);
    }

    public function record(Request $request, string $kind, int $id)
    {
        abort_unless(in_array($kind, PurchaseExpenseService::KINDS, true), 404);
        $item = $this->purchases->find($kind, $id);

        try {
            $expense = $this->purchases->record($kind, $item, $request->validate($this->purchases->recordRules()), auth()->id());
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$item->name}: TZS ".number_format((float) $expense->amount).' recorded under Expenses.');
    }

    public function link(Request $request, string $kind, int $id)
    {
        abort_unless(in_array($kind, PurchaseExpenseService::KINDS, true), 404);
        $item = $this->purchases->find($kind, $id);
        $data = $request->validate(['expense_id' => ['required', 'integer', Rule::exists('finance_expenses', 'id')]]);

        try {
            $this->purchases->link($kind, $item, FinanceExpense::findOrFail($data['expense_id']));
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$item->name} is linked to the expense you already recorded; it will not be counted twice.");
    }
}
