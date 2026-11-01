<?php

namespace App\Services\Finance;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceMoneyTransfer;
use App\Models\Payment;
use App\Support\FinanceDates;
use App\Support\FinanceStart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Double entry view of Finance (Mwongozo wa Fedha §3): every movement as a
 * journal line with a debit side (where the money went) and a credit side
 * (where it came from), and a trial balance per ledger account. Built from
 * the records Finance already keeps; nothing is stored twice.
 *
 *   Sale            Dr Cash: <money account>      Cr Sales: <product>
 *   Owner capital   Dr Cash: <money account>      Cr Owner capital: <source>
 *   Loan received   Dr Cash: <money account>      Cr Loan: <source>
 *   Loan repaid     Dr Loan: <source>             Cr Cash: <money account>
 *   Expense         Dr Expense: <type/category>   Cr Cash: <money account>
 *   Transfer        Dr Cash: <to>                 Cr Cash: <from>
 *   Opening         Dr Cash: <money account>      Cr Opening balance
 *   Loan brought    Dr Opening balance            Cr Loan: <source>
 *
 * Once Finance has a start date (FinanceStart) the books open that day: each
 * money account with what it held, each loan with what was still owed, and
 * only later movements. The ones before stay under "Unorganised data".
 */
class LedgerService
{
    public const UNASSIGNED_CASH = 'Cash: account not set';

    public const OPENING = 'Opening balance';

    /** Separate journals (books of entry), each for one kind of movement. */
    public const JOURNALS = [
        'sales' => ['label' => 'Sales', 'kinds' => ['sale'], 'note' => 'Customer payments. Dr the money account they landed in, Cr Sales for the product.'],
        'expenses' => ['label' => 'Expenses', 'kinds' => ['expense'], 'note' => 'Money spent. Dr the expense type, Cr the money account it was paid from.'],
        'capital' => ['label' => 'Capital & loans', 'kinds' => ['capital', 'loan'], 'note' => 'Owner capital and loans received. Dr the money account, Cr the owner or lender.'],
        'repayments' => ['label' => 'Loan repayments', 'kinds' => ['repayment'], 'note' => 'Loans paid back. Dr the lender (less owed), Cr the money account.'],
        'transfers' => ['label' => 'Transfers', 'kinds' => ['transfer'], 'note' => 'Money moved between our own accounts. Dr the receiving account, Cr the sending one.'],
        'opening' => ['label' => 'Opening balances', 'kinds' => ['opening'], 'note' => 'What the books started with on the Finance start day: money in each account (Dr) and loans still owed (Cr).'],
    ];

    /** Lines of one journal ('all' = every line). */
    public function forJournal(Collection $journal, string $key): Collection
    {
        $kinds = self::JOURNALS[$key]['kinds'] ?? null;

        return $kinds === null ? $journal : $journal->whereIn('kind', $kinds)->values();
    }

    /**
     * Journal lines of the books, or (with $old) of the unorganised records
     * before the Finance start date.
     *
     * @return Collection<int, array{date: Carbon, kind: string, description: string, ref: ?string, dr: string, cr: string, amount: float, url: ?string}>
     */
    public function journal(?Carbon $from = null, ?Carbon $to = null, bool $old = false): Collection
    {
        $ready = MoneyAccountService::ready();
        $accounts = $ready ? FinanceMoneyAccount::pluck('name', 'id') : collect();
        $methodMap = $ready ? collect(app(MoneyAccountService::class)->methodMap())->map->name : collect();
        $cash = fn ($id) => $id && isset($accounts[$id]) ? 'Cash: '.$accounts[$id] : self::UNASSIGNED_CASH;
        $lines = collect();

        FinanceStart::scopeMoment(Payment::where('status', 'approved'), 'created_at', $old)
            ->with('order:id,order_number,product_id,tool_id', 'order.product:id,name', 'order.tool:id,name')
            ->get()
            ->each(function (Payment $p) use ($lines, $methodMap) {
                $lines->push($this->line(FinanceDates::payment($p), 'sale',
                    'Sale '.($p->order?->order_number ?? ''), $p->transaction_reference,
                    isset($methodMap[$p->payment_method]) ? 'Cash: '.$methodMap[$p->payment_method] : self::UNASSIGNED_CASH,
                    'Sales: '.($p->order?->itemName() ?? 'Other'),
                    (float) $p->amount, route('admin.payments.show', $p), 1, $p->id));
            });

        FinanceCapitalEntry::all()->each(function (FinanceCapitalEntry $c) use ($lines, $cash) {
            $lines->push($this->line(FinanceDates::capital($c), $c->is_loan ? 'loan' : 'capital',
                ($c->is_loan ? 'Loan received: ' : 'Capital: ').$c->label, null,
                $cash($c->money_account_id ?? null),
                ($c->is_loan ? 'Loan: ' : 'Owner capital: ').$this->person($c->source),
                (float) $c->amount, route('admin.finance.capital.edit', $c), 2, $c->id));
        });

        if ($ready) {
            FinanceMoneyTransfer::with('fromAccount:id,name', 'toAccount:id,name')->get()->each(function (FinanceMoneyTransfer $t) use ($lines) {
                $lines->push($this->line($t->transferred_at, 'transfer',
                    'Transfer '.($t->fromAccount?->name).' → '.($t->toAccount?->name), $t->reference,
                    'Cash: '.($t->toAccount?->name), 'Cash: '.($t->fromAccount?->name),
                    (float) $t->amount, null, 3, $t->id));
            });
        }

        FinanceExpense::all()->each(function (FinanceExpense $e) use ($lines, $cash) {
            $lines->push($this->line($e->spent_at, 'expense', $e->label, null,
                'Expense: '.($e->typeLabel() ?? $e->category ?? 'Uncategorised'),
                $cash($e->money_account_id ?? null),
                (float) $e->amount, route('admin.finance.expenses.edit', $e), 4, $e->id));
        });

        FinanceLoanRepayment::with('capitalEntry:id,label,source')->get()->each(function (FinanceLoanRepayment $r) use ($lines, $cash) {
            $lines->push($this->line($r->paid_at, 'repayment', 'Loan repaid: '.($r->capitalEntry?->label ?? 'loan'), $r->reference,
                'Loan: '.$this->person($r->capitalEntry?->source),
                $cash($r->money_account_id ?? null),
                (float) $r->amount,
                $r->capitalEntry ? route('admin.finance.capital.repayments.index', $r->capitalEntry) : null, 5, $r->id));
        });

        $lines = $lines->filter(fn ($l) => FinanceStart::keeps($l['date'], $old));
        if (! $old && FinanceStart::isSet()) {
            $lines = $lines->merge($this->opening($ready));
        }

        return $lines
            ->filter(fn ($l) => ($from === null || $l['date']->gte($from)) && ($to === null || $l['date']->lte($to)))
            ->sortBy('sort')
            ->values()
            ->map(function ($l) {
                unset($l['sort']);

                return $l;
            });
    }

    /**
     * Debit and credit totals per ledger account; the two columns are always equal.
     *
     * @return Collection<int, array{account: string, group: string, dr: float, cr: float, balance: float}>
     */
    public function trialBalance(Collection $journal): Collection
    {
        $rows = [];
        foreach ($journal as $l) {
            $rows[$l['dr']]['dr'] = ($rows[$l['dr']]['dr'] ?? 0) + $l['amount'];
            $rows[$l['cr']]['cr'] = ($rows[$l['cr']]['cr'] ?? 0) + $l['amount'];
        }

        $order = ['Cash' => 1, 'Sales' => 2, 'Owner capital' => 3, self::OPENING => 4, 'Loan' => 5, 'Expense' => 6];

        return collect($rows)->map(function ($r, $name) {
            $dr = round($r['dr'] ?? 0, 2);
            $cr = round($r['cr'] ?? 0, 2);

            return ['account' => $name, 'group' => strstr($name, ':', true) ?: $name, 'dr' => $dr, 'cr' => $cr, 'balance' => round($dr - $cr, 2)];
        })
            ->sortBy(fn ($r) => sprintf('%d|%s', $order[$r['group']] ?? 9, $r['account']))
            ->values();
    }

    /** Opening lines on the start day: money in each account, and loans still owed then. */
    private function opening(bool $ready): Collection
    {
        $start = FinanceStart::date();
        $lines = collect();

        if ($ready) {
            foreach (app(MoneyAccountService::class)->balances() as $b) {
                $a = $b['account'];
                $window = app(MoneyAccountService::class)->window($a);
                if (round($b['opening'], 2) == 0 || ! $window['from']?->equalTo($start)) {
                    continue;
                }
                [$dr, $cr] = $b['opening'] > 0 ? ['Cash: '.$a->name, self::OPENING] : [self::OPENING, 'Cash: '.$a->name];
                $lines->push($this->line($start, 'opening', 'Opening balance: '.$a->name, null, $dr, $cr,
                    abs($b['opening']), route('admin.finance.money.show', $a->id), 0, $a->id));
            }
        }

        foreach (app(LoanService::class)->broughtForward() as $loan) {
            $c = $loan['loan'];
            $lines->push($this->line($start, 'opening', 'Loan brought forward: '.$c->label, null, self::OPENING,
                'Loan: '.$this->person($c->source), $loan['outstanding'],
                route('admin.finance.capital.repayments.index', $c), 0, 1000000 + $c->id));
        }

        return $lines;
    }

    /** "Dr. Mussa Awadhi" and "Mr. Mussa Awadhi" are one lender. */
    private function person(?string $source): string
    {
        $name = trim(preg_replace('/^(dr|mr|mrs|ms|miss|prof)\.?\s+/i', '', (string) $source));

        return $name !== '' ? $name : 'Unknown';
    }

    private function line($date, string $kind, string $description, ?string $ref, string $dr, string $cr, float $amount, ?string $url, int $order, int $id): array
    {
        $date = Carbon::parse($date ?? now());

        return [
            'date' => $date,
            'kind' => $kind,
            'description' => trim($description),
            'ref' => $ref,
            'dr' => $dr,
            'cr' => $cr,
            'amount' => round($amount, 2),
            'url' => $url,
            'sort' => $date->format('Y-m-d').sprintf('|%d|%010d', $order, $id),
        ];
    }
}
