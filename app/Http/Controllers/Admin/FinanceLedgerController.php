<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finance\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Finance → Ledger: trial balance, all entries, and one tab per journal
 * (sales, expenses, capital & loans, loan repayments, transfers).
 */
class FinanceLedgerController extends Controller
{
    public function index(Request $request, LedgerService $ledger)
    {
        $all = $ledger->journal();
        $months = $all->map(fn ($l) => $l['date']->format('Y-m'))->unique()->sortDesc()->values();

        $month = (string) $request->query('month', '');
        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
            $journal = $all->filter(fn ($l) => $l['date']->between($start, $start->copy()->endOfMonth()))->values();
        } else {
            $month = '';
            $journal = $all;
        }

        $tabs = ['trial' => 'Trial balance', 'all' => 'All entries'] + collect(LedgerService::JOURNALS)->map->label->all();
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'trial';

        $counts = ['all' => $journal->count()];
        foreach (array_keys(LedgerService::JOURNALS) as $key) {
            $counts[$key] = $ledger->forJournal($journal, $key)->count();
        }

        return view('admin.finance.ledger', [
            'tabs' => $tabs,
            'tab' => $tab,
            'counts' => $counts,
            'lines' => $tab === 'trial' ? collect() : $ledger->forJournal($journal, $tab),
            'trial' => $ledger->trialBalance($journal),
            'months' => $months,
            'month' => $month,
        ]);
    }
}
