<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finance\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Finance → Ledger: journal (Dr / Cr per movement) and trial balance. */
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

        return view('admin.finance.ledger', [
            'journal' => $journal,
            'trial' => $ledger->trialBalance($journal),
            'months' => $months,
            'month' => $month,
        ]);
    }
}
