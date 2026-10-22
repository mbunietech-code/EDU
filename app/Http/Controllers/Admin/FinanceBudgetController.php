<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finance\BudgetService;
use App\Services\Finance\FinanceException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Finance → Budget: each month's income split by percentage, against actual spending. */
class FinanceBudgetController extends Controller
{
    public function index(Request $request, BudgetService $budget)
    {
        $months = $budget->months();
        $month = (string) $request->query('month', $months[0] ?? now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        return view('admin.finance.budget', [
            'report' => $budget->month(Carbon::createFromFormat('!Y-m', $month)),
            'months' => $months,
            'buckets' => $budget->buckets(),
            'capital' => $budget->capitalShares(),
        ]);
    }

    public function update(Request $request, BudgetService $budget)
    {
        $data = $request->validate($budget->percentRules());

        try {
            $budget->savePercents($data['percents']);
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Budget percentages saved.');
    }
}
