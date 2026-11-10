<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\FinanceAccess;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinanceAllocation;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceMoneyTransfer;
use App\Models\Payment;
use App\Services\DeletionService;
use App\Services\Finance\FinanceAuditService;
use App\Services\Finance\MoneyAccountService;
use App\Services\FinanceOverviewService;
use App\Support\FinanceDates;
use App\Support\FinancePeriod;
use App\Support\FinanceStart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Finance pages the MHub app did not have yet: Audit, one line's records,
 * the start date, "Unorganised data" (records from before the start date,
 * read-only) and editing / removing a money transfer. Finance permission
 * and the PIN unlock apply, as everywhere in Finance.
 */
class FinanceExtrasController extends Controller
{
    use FinanceAccess;

    private static function day($date): ?string
    {
        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    // ================================================================ audit

    public function audit(Request $request, FinanceAuditService $audit): JsonResponse
    {
        $this->finance($request);

        $kind = in_array($request->query('kind'), ['added', 'changed', 'removed'], true) ? $request->query('kind') : null;
        $activity = $audit->activity($request->boolean('more') ? 1000 : 150);
        if ($kind) {
            $activity = $activity->where('kind', $kind)->values();
        }

        return response()->json([
            'checks' => collect($audit->checks())->map(fn ($c) => [
                'key' => $c['key'],
                'title' => $c['title'],
                'severity' => $c['severity'],
                'help' => $c['help'],
                'items' => collect($c['items'])->map(fn ($i) => ['label' => $i['label'], 'detail' => $i['detail'] ?? null])->values(),
            ])->values(),
            'activity' => $activity->map(fn ($a) => [
                'at' => optional($a['at'])->toIso8601String(),
                'who' => $a['who'],
                'kind' => $a['kind'],
                'what' => $a['what'],
                'detail' => $a['detail'],
            ])->values(),
            'kind' => $kind,
        ]);
    }

    // ================================================================= line

    /** One overview line (product, tool, cost type…): every record behind its numbers. */
    public function line(Request $request, FinanceOverviewService $overview): JsonResponse
    {
        $this->finance($request);

        $period = FinancePeriod::fromRequest($request);
        $line = $overview->line((string) $request->query('line', ''), $period);
        abort_if($line === null, 404, 'That line has no records.');

        $names = MoneyAccountService::ready() ? FinanceMoneyAccount::pluck('name', 'id') : collect();

        return response()->json([
            'period' => ['key' => $period->key, 'label' => $period->label()],
            'row' => collect($line['row'])->only(['key', 'label', 'type', 'capital', 'income', 'expenses', 'repaid', 'balance']),
            'income' => $line['income']->map(fn (Payment $p) => [
                'date' => self::day(FinanceDates::payment($p)),
                'label' => trim(($p->order?->order_number ? '#'.$p->order->order_number.' · ' : '').($p->user?->name ?? 'Customer')),
                'detail' => $p->payment_method,
                'amount' => (float) $p->amount,
            ])->values(),
            'capital' => $line['capital']->map(fn (FinanceCapitalEntry $c) => [
                'date' => self::day(FinanceDates::capital($c)),
                'label' => $c->label,
                'detail' => trim(($c->is_loan ? 'Loan' : 'Capital').($c->source ? ' · '.$c->source : '')),
                'amount' => (float) $c->amount,
            ])->values(),
            'expenses' => $line['expenses']->map(fn (FinanceExpense $e) => [
                'id' => $e->id,
                'date' => self::day($e->spent_at),
                'label' => $e->label,
                'detail' => $e->money_account_id ? 'Paid from '.($names[$e->money_account_id] ?? 'account') : 'No account',
                'amount' => (float) $e->amount,
            ])->values(),
            'repaid' => $line['repaid']->map(fn (FinanceLoanRepayment $r) => [
                'date' => self::day($r->paid_at),
                'label' => $r->capitalEntry?->label ?? 'Loan repayment',
                'detail' => $r->method,
                'amount' => (float) $r->amount,
            ])->values(),
            'given' => $line['given']->map(fn (FinanceAllocation $g) => [
                'date' => self::day($g->allocated_at),
                'label' => 'Given from '.($g->moneyAccount?->name ?? $names[$g->money_account_id] ?? 'account'),
                'detail' => $g->notes ?? null,
                'amount' => (float) $g->amount,
            ])->values(),
        ]);
    }

    // ============================================================ start date

    public function start(Request $request, MoneyAccountService $money): JsonResponse
    {
        $this->finance($request);

        $date = FinanceDates::day($request->query('date')) ?? FinanceStart::date() ?? FinanceDates::day(FinanceDates::today());

        return response()->json([
            'start' => FinanceStart::date()?->toDateString(),
            'start_label' => FinanceStart::label(),
            'date' => $date->toDateString(),
            'today' => FinanceDates::today(),
            'accounts' => MoneyAccountService::ready()
                ? FinanceMoneyAccount::orderByDesc('is_active')->orderBy('name')->get()->map(fn (FinanceMoneyAccount $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'is_active' => (bool) $a->is_active,
                    'records' => (float) $money->balanceBefore($a, $date),
                    'counted' => FinanceStart::balanceFor($a->id),
                ])->values()
                : [],
        ]);
    }

    public function saveStart(Request $request): JsonResponse
    {
        $this->finance($request);

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

        return response()->json(['message' => 'Finance now counts from '.FinanceStart::label().'. Earlier records are kept under Unorganised data.']);
    }

    public function clearStart(Request $request): JsonResponse
    {
        $this->finance($request);

        $before = FinanceStart::date()?->toDateString();
        FinanceStart::clear();
        ActivityLog::log('finance_start_cleared', 'Finance', null, ['before' => $before]);

        return response()->json(['message' => 'Finance counts every record again.']);
    }

    // ======================================================== unorganised

    /** Records from before the start date, read-only (?tab=summary|sales|expenses|capital|repayments|transfers|given). */
    public function unorganised(Request $request, FinanceOverviewService $overview, MoneyAccountService $money): JsonResponse
    {
        $this->finance($request);

        $start = FinanceStart::date();
        $tab = in_array($request->query('tab'), ['summary', 'sales', 'expenses', 'capital', 'repayments', 'transfers', 'given'], true)
            ? (string) $request->query('tab') : 'summary';
        $base = ['start' => $start?->toDateString(), 'start_label' => FinanceStart::label(), 'tab' => $tab];

        if ($start === null) {
            return response()->json($base + ['data' => [], 'meta' => null]);
        }

        if ($tab === 'summary') {
            $rows = $overview->rows(null, true);
            $totals = $overview->totals($rows);
            $rows = array_values(array_filter($rows, fn ($r) => $r['key'] !== FinanceOverviewService::POOL
                && ($r['capital'] || $r['income'] || $r['expenses'] || $r['repaid'])));

            return response()->json($base + [
                'totals' => collect($totals)->only(['capital', 'income', 'expenses', 'repaid', 'balance'])->map(fn ($v) => (float) $v),
                'data' => collect($rows)->map(fn ($r) => collect($r)->only(['label', 'type', 'capital', 'income', 'expenses', 'repaid', 'balance']))->values(),
                'accounts' => MoneyAccountService::ready()
                    ? $money->balances(true, true)->map(fn ($b) => [
                        'name' => $b['account']->name,
                        'balance' => (float) $b['balance'],
                        'counted_at_start' => FinanceStart::balanceFor($b['account']->id),
                    ])->values()
                    : [],
                'meta' => null,
            ]);
        }

        $page = match ($tab) {
            'sales' => FinanceStart::scopeMoment(Payment::where('status', 'approved'), 'created_at', true)
                ->with('order:id,order_number', 'user:id,name')->latest('created_at')->paginate(50),
            'expenses' => FinanceStart::scopeDay(FinanceExpense::query(), 'spent_at', true)
                ->latest('spent_at')->latest('id')->paginate(50),
            'capital' => FinanceCapitalEntry::inBooks(true)->withSum('repayments', 'amount')->latest()->paginate(50),
            'repayments' => FinanceStart::scopeDay(FinanceLoanRepayment::with('capitalEntry:id,label'), 'paid_at', true)
                ->latest('paid_at')->paginate(50),
            'transfers' => MoneyAccountService::ready()
                ? FinanceStart::scopeDay(FinanceMoneyTransfer::with('fromAccount:id,name', 'toAccount:id,name'), 'transferred_at', true)
                    ->latest('transferred_at')->paginate(50)
                : null,
            'given' => FinanceAllocation::ready()
                ? FinanceStart::scopeDay(FinanceAllocation::with('moneyAccount:id,name'), 'allocated_at', true)
                    ->latest('allocated_at')->paginate(50)
                : null,
        };

        if ($page === null) {
            return response()->json($base + ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]]);
        }

        $rows = collect($page->items())->map(fn ($r) => match ($tab) {
            'sales' => ['date' => self::day(FinanceDates::payment($r)), 'label' => trim(($r->order?->order_number ? '#'.$r->order->order_number.' · ' : '').($r->user?->name ?? 'Customer')), 'detail' => $r->payment_method, 'amount' => (float) $r->amount],
            'expenses' => ['date' => self::day($r->spent_at), 'label' => $r->label, 'detail' => $r->category ?? null, 'amount' => (float) $r->amount],
            'capital' => ['date' => self::day(FinanceDates::capital($r)), 'label' => $r->label, 'detail' => trim(($r->is_loan ? 'Loan' : 'Capital').($r->source ? ' · '.$r->source : '').($r->is_loan ? ' · repaid TZS '.number_format((float) $r->repayments_sum_amount) : '')), 'amount' => (float) $r->amount],
            'repayments' => ['date' => self::day($r->paid_at), 'label' => $r->capitalEntry?->label ?? 'Loan repayment', 'detail' => $r->method, 'amount' => (float) $r->amount],
            'transfers' => ['date' => self::day($r->transferred_at), 'label' => ($r->fromAccount?->name ?? '?').' → '.($r->toAccount?->name ?? '?'), 'detail' => $r->reference, 'amount' => (float) $r->amount],
            'given' => ['date' => self::day($r->allocated_at), 'label' => 'Given from '.($r->moneyAccount?->name ?? 'account').' to '.($r->label ?? 'a line'), 'detail' => null, 'amount' => (float) $r->amount],
        });

        return response()->json($base + [
            'data' => $rows->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    // =========================================================== transfers

    public function transfer(Request $request, int $transfer): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        $t = FinanceMoneyTransfer::with('fromAccount:id,name', 'toAccount:id,name')->findOrFail($transfer);

        return response()->json(['data' => [
            'id' => $t->id,
            'from_account_id' => $t->from_account_id,
            'from_name' => $t->fromAccount?->name,
            'to_account_id' => $t->to_account_id,
            'to_name' => $t->toAccount?->name,
            'amount' => (float) $t->amount,
            'transferred_at' => self::day($t->transferred_at),
            'reference' => $t->reference,
            'notes' => $t->notes,
            'read_only' => FinanceStart::isOld(FinanceDates::day($t->transferred_at)),
            'accounts' => FinanceMoneyAccount::orderBy('name')->get(['id', 'name', 'is_active'])
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'is_active' => (bool) $a->is_active])->values(),
        ]]);
    }

    public function updateTransfer(Request $request, int $transfer, MoneyAccountService $money): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        $t = FinanceMoneyTransfer::findOrFail($transfer);
        FinanceStart::guard(FinanceDates::day($t->transferred_at), 'transferred_at');
        $t->fill($request->validate($money->transferRules()));
        $changes = collect($t->getDirty())->except('updated_at')
            ->map(fn ($new, $field) => [$this->plain($t->getOriginal($field)), $this->plain($new)])->all();
        $t->save();
        ActivityLog::log('finance_money_transfer_updated', 'FinanceMoneyTransfer', $t->id, [
            'amount' => $t->amount, 'from' => $t->from_account_id, 'to' => $t->to_account_id, 'changes' => $changes,
        ]);

        return response()->json(['message' => 'Transfer updated.']);
    }

    public function destroyTransfer(Request $request, int $transfer, DeletionService $deletion): JsonResponse
    {
        $this->finance($request);
        abort_unless(MoneyAccountService::ready(), 409, 'Apply database change 0034 first.');

        $t = FinanceMoneyTransfer::findOrFail($transfer);
        FinanceStart::guard(FinanceDates::day($t->transferred_at), 'reason');
        $deletion->delete($t, $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason']);

        return response()->json(['message' => 'Transfer removed.']);
    }

    private function plain($value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }
}
