<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinanceAllocation;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceMoneyTransfer;
use App\Models\Payment;
use App\Services\Finance\LedgerService;
use App\Services\Finance\LoanService;
use App\Services\Finance\MoneyAccountService;
use App\Services\FinanceOverviewService;
use App\Support\FinanceDates;
use App\Support\FinanceStart;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Unorganised data": every Finance record from before the start date
 * (FinanceStart), read-only. Nothing here is deleted, moved or changed;
 * customer payments are only read. Also the form that sets the start date
 * and the money counted in each account on it.
 */
class FinanceUnorganisedController extends Controller
{
    public const TABS = [
        'summary' => 'Summary',
        'sales' => 'Sales',
        'expenses' => 'Expenses',
        'capital' => 'Capital & loans',
        'repayments' => 'Loan repayments',
        'transfers' => 'Transfers',
        'given' => 'Given money',
        'ledger' => 'Ledger',
    ];

    public function index(Request $request, FinanceOverviewService $overview, MoneyAccountService $money, LedgerService $ledger, LoanService $loans)
    {
        $start = FinanceStart::date();
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'summary';
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? (string) $request->query('month') : '';
        $data = ['start' => $start, 'tabs' => self::TABS, 'tab' => $tab, 'month' => $month];

        if ($start === null) {
            return view('admin.finance.unorganised', $data);
        }

        $months = $this->months($start);
        $data['months'] = $months;

        $data += match ($tab) {
            'summary' => $this->summary($overview, $money, $loans),
            'sales' => ['records' => $this->inMonth(
                FinanceStart::scopeMoment(Payment::where('status', 'approved'), 'created_at', true)
                    ->with('order:id,order_number,product_id,tool_id', 'order.product:id,name', 'order.tool:id,name', 'user:id,name'),
                'created_at', $month, true)->latest('created_at')->paginate(50)->withQueryString()],
            'expenses' => ['records' => $this->inMonth(
                FinanceStart::scopeDay(FinanceExpense::with('product:id,name', 'tool:id,name', 'creator:id,name'), 'spent_at', true),
                'spent_at', $month)->latest('spent_at')->latest('id')->paginate(50)->withQueryString()],
            'capital' => ['records' => FinanceCapitalEntry::inBooks(true)->with('product:id,name', 'tool:id,name')
                ->withSum('repayments', 'amount')->latest()->paginate(50)->withQueryString()],
            'repayments' => ['records' => FinanceStart::scopeDay(FinanceLoanRepayment::with('capitalEntry:id,label,source'), 'paid_at', true)
                ->latest('paid_at')->paginate(50)->withQueryString()],
            'transfers' => ['records' => MoneyAccountService::ready()
                ? FinanceStart::scopeDay(FinanceMoneyTransfer::with('fromAccount:id,name', 'toAccount:id,name'), 'transferred_at', true)
                    ->latest('transferred_at')->paginate(50)->withQueryString()
                : null],
            'given' => ['records' => FinanceAllocation::ready()
                ? FinanceStart::scopeDay(FinanceAllocation::with('moneyAccount:id,name'), 'allocated_at', true)
                    ->latest('allocated_at')->paginate(50)->withQueryString()
                : null],
            'ledger' => (function () use ($ledger, $month) {
                $lines = $ledger->journal(null, null, true);
                if ($month !== '') {
                    $lines = $lines->filter(fn ($l) => $l['date']->format('Y-m') === $month)->values();
                }

                return ['lines' => $lines, 'trial' => $ledger->trialBalance($lines)];
            })(),
        };

        return view('admin.finance.unorganised', $data);
    }

    /** Every journal line before the start date, as a spreadsheet. */
    public function csv(LedgerService $ledger): StreamedResponse
    {
        abort_unless(FinanceStart::isSet(), 404);

        $lines = $ledger->journal(null, null, true);
        $name = 'finance-unorganised-before-'.FinanceStart::date()->toDateString().'.csv';

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Kind', 'Description', 'Reference', 'Debit (Dr)', 'Credit (Cr)', 'Amount (TZS)']);
            foreach ($lines as $l) {
                fputcsv($out, [$l['date']->toDateString(), $l['kind'], $l['description'], $l['ref'], $l['dr'], $l['cr'], $l['amount']]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** The start date and, per money account, the money counted in it that day. */
    public function start(Request $request, MoneyAccountService $money)
    {
        $date = FinanceDates::day($request->query('date')) ?? FinanceStart::date() ?? FinanceDates::day(FinanceDates::today());
        $ready = MoneyAccountService::ready();

        $accounts = $ready
            ? FinanceMoneyAccount::orderByDesc('is_active')->orderBy('name')->get()->map(fn ($a) => [
                'account' => $a,
                'records' => $money->balanceBefore($a, $date),
                'counted' => FinanceStart::balanceFor($a->id),
            ])
            : collect();

        return view('admin.finance.start', [
            'date' => $date,
            'start' => FinanceStart::date(),
            'accounts' => $accounts,
        ]);
    }

    public function saveStart(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'before_or_equal:'.FinanceDates::today()],
            'balances' => ['nullable', 'array'],
            'balances.*' => ['nullable', 'numeric', 'min:-9999999999', 'max:9999999999'],
        ]);

        $before = ['date' => FinanceStart::date()?->toDateString(), 'balances' => FinanceStart::balances()];
        $ids = MoneyAccountService::ready() ? FinanceMoneyAccount::pluck('id')->all() : [];
        $balances = array_intersect_key($validated['balances'] ?? [], array_flip($ids));
        $date = FinanceDates::day($validated['start_date'])->toDateString();

        FinanceStart::save($date, $balances);
        ActivityLog::log('finance_start_set', 'Finance', null, [
            'date' => $date,
            'balances' => FinanceStart::balances(),
            'before' => $before,
        ]);

        return redirect()->route('admin.finance.dashboard')
            ->with('success', 'Finance now counts from '.FinanceStart::label().'. Earlier records are kept under Unorganised data.');
    }

    public function clearStart()
    {
        $before = FinanceStart::date()?->toDateString();
        FinanceStart::clear();
        ActivityLog::log('finance_start_cleared', 'Finance', null, ['before' => $before]);

        return redirect()->route('admin.finance.dashboard')->with('success', 'Finance counts every record again.');
    }

    private function summary(FinanceOverviewService $overview, MoneyAccountService $money, LoanService $loans): array
    {
        $rows = $overview->rows(null, true);
        $totals = $overview->totals($rows);
        $rows = array_values(array_filter($rows, fn ($r) => $r['key'] !== FinanceOverviewService::POOL
            && ($r['capital'] || $r['income'] || $r['expenses'] || $r['repaid'])));

        $accounts = MoneyAccountService::ready()
            ? $money->balances(true, true)->map(fn ($b) => $b + ['counted_at_start' => FinanceStart::balanceFor($b['account']->id)])
            : collect();

        return [
            'rows' => $rows,
            'totals' => $totals,
            'accounts' => $accounts,
            'unassigned' => MoneyAccountService::ready() ? $money->unassigned(true)['totals'] : null,
            'broughtForward' => $loans->broughtForward(),
        ];
    }

    /** Months that have records before the start, newest first. */
    private function months(Carbon $start): array
    {
        $months = FinanceStart::scopeMoment(Payment::where('status', 'approved'), 'created_at', true)->get(['created_at', 'reviewed_at'])
            ->map(fn ($p) => FinanceDates::payment($p)?->format('Y-m'))
            ->merge(FinanceStart::scopeDay(FinanceExpense::whereNotNull('spent_at'), 'spent_at', true)->pluck('spent_at')->map(fn ($d) => Carbon::parse($d)->format('Y-m')));

        return $months->filter()->unique()->sortDesc()->values()->all();
    }

    /** Limit a query to one month (business time for a timestamp, as typed for a date). */
    private function inMonth($query, string $column, string $month, bool $moment = false)
    {
        if ($month === '') {
            return $query;
        }
        $from = Carbon::createFromFormat('!Y-m', $month, FinanceDates::tz())->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return $moment
            ? $query->whereBetween($column, [$from->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'), $to->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s')])
            : $query->where($column, '>=', $from->toDateString())->where($column, '<', $to->copy()->addDay()->toDateString());
    }
}
