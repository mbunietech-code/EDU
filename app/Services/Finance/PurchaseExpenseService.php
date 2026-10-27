<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\CompanyAsset;
use App\Models\FinanceExpense;
use App\Services\CurrencyRateService;
use App\Support\FinanceDates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turning what we bought into Finance expenses (Mwongozo wa Fedha §2):
 * product accounts (Accounts → Plans, with a cost) and company assets
 * (domain, hosting, server, subscriptions…). Each payment is recorded once
 * per month (source "account:YYYY-MM" / "asset:YYYY-MM").
 *
 * New purchases and renewals record themselves (autoRecord, from the models'
 * saved event). Older items bought before that are recorded or linked to an
 * expense someone already typed in by hand, once, from Finance → Purchases.
 */
class PurchaseExpenseService
{
    public const KINDS = ['account', 'asset'];

    /** Asset category → expense type. */
    private const ASSET_TYPES = [
        'domain' => 'hosting', 'hosting' => 'hosting', 'server' => 'hosting',
        'device' => 'equipment',
        'software' => 'other', 'subscription' => 'other', 'license' => 'other', 'service' => 'other', 'other' => 'other',
    ];

    public function __construct(private AutoExpenseService $auto, private CurrencyRateService $rates)
    {
    }

    public function accounts(): Collection
    {
        return Account::with('product:id,name')->whereNotNull('cost')->where('cost', '>', 0)
            ->where('status', '!=', 'archived')->orderBy('name')->get();
    }

    public function assets(): Collection
    {
        return CompanyAsset::whereNotNull('cost')->where('cost', '>', 0)->orderBy('name')->get();
    }

    /** @return array<int, Collection> source id => its expenses (newest first) */
    public function recorded(string $kind): array
    {
        if (! FinanceExpense::typesReady()) {
            return [];
        }

        return FinanceExpense::where('source_type', 'like', $kind.':%')->orderByDesc('spent_at')->get()
            ->groupBy('source_id')->all();
    }

    /**
     * A purchase just made or renewed (a new item, or its purchase date moved):
     * recorded under Expenses by itself, paid from the money account used last.
     * Nothing happens without a cost and a date, for a date still to come, when
     * a foreign price has no rate yet, or when that month is already recorded —
     * those wait on the Purchases page.
     */
    public function autoRecord(string $kind, $item): ?FinanceExpense
    {
        if (! FinanceExpense::typesReady() || ! $item->purchased_at || (float) $item->cost <= 0) {
            return null;
        }
        if ($kind === 'account' && $item->status === 'archived') {
            return null;
        }

        $date = $item->purchased_at->toDateString();
        $amount = $this->costInTzs((float) $item->cost, $kind === 'account' ? $item->cost_currency : $item->currency);
        if ($date > FinanceDates::today() || ! $amount || $this->auto->find("{$kind}:".substr($date, 0, 7), $item->id)) {
            return null;
        }

        try {
            return $this->record($kind, $item, [
                'amount' => $amount,
                'spent_at' => $date,
                'money_account_id' => app(MoneyAccountService::class)->lastUsedId(),
            ], auth()->id());
        } catch (FinanceException $e) {
            return null;
        }
    }

    /** Items with a cost that have no expense at all yet (bought before purchases recorded themselves). */
    public function pending(string $kind, Collection $items): Collection
    {
        $recorded = $this->recorded($kind);

        return $items->reject(fn ($item) => isset($recorded[$item->id]))->values();
    }

    /** Expenses typed in by hand that nothing is linked to yet (candidates for "already recorded"). */
    public function linkable(): Collection
    {
        return FinanceExpense::typesReady()
            ? FinanceExpense::whereNull('source_type')->orderByDesc('spent_at')->get(['id', 'label', 'amount', 'spent_at'])
            : collect();
    }

    /** Cost in TZS: as entered when it is TZS, else converted at today's rate (null when unknown). */
    public function costInTzs(float $cost, ?string $currency): ?float
    {
        $currency = strtoupper((string) ($currency ?: 'TZS'));
        if ($currency === 'TZS') {
            return round($cost, 2);
        }
        $rate = $this->rates->rates()[$currency] ?? null; // TZS → currency

        return $rate ? round($cost / $rate, 0) : null;
    }

    public function defaultType(string $kind, $item): string
    {
        return $kind === 'account' ? 'product_accounts' : (self::ASSET_TYPES[$item->category] ?? 'other');
    }

    public function find(string $kind, int $id)
    {
        return $kind === 'account' ? Account::findOrFail($id) : CompanyAsset::findOrFail($id);
    }

    public function recordRules(): array
    {
        return MoneyAccountService::fieldRules() + FinanceExpense::typeRules() + [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'spent_at' => ['required', 'date', 'before_or_equal:'.\App\Support\FinanceDates::today()],
        ];
    }

    /** @throws FinanceException when that month's payment is already recorded */
    public function record(string $kind, $item, array $data, ?int $userId): FinanceExpense
    {
        $this->assertReady();
        $month = Carbon::parse($data['spent_at'])->format('Y-m');
        $source = "{$kind}:{$month}";

        if ($this->auto->find($source, $item->id)) {
            throw new FinanceException("{$item->name} is already recorded for ".Carbon::parse($data['spent_at'])->format('F Y').'.');
        }

        $isAccount = $kind === 'account';

        return $this->auto->record($source, $item->id, [
            'label' => $isAccount ? ($item->product?->name ?? $item->name) : $item->name,
            'amount' => (float) $data['amount'],
            'spent_at' => $data['spent_at'],
            'expense_type' => $data['expense_type'] ?? $this->defaultType($kind, $item),
            'product_id' => $isAccount ? $item->product_id : null,
            'description' => $isAccount
                ? trim("Account {$item->name}".($item->plan_name ? " · {$item->plan_name}" : '').$this->costNote($item->cost, $item->cost_currency))
                : trim(($item->vendor ? "{$item->vendor} · " : '').($item->categoryLabel() ?? '').$this->costNote($item->cost, $item->currency)),
            'money_account_id' => $data['money_account_id'] ?? null,
        ], $userId);
    }

    /**
     * Mark a hand-typed expense as this item's payment for its month, so it is
     * shown as recorded and never recorded again. Only the expense's source
     * link changes; its amount, date and label stay as typed.
     *
     * @throws FinanceException
     */
    public function link(string $kind, $item, FinanceExpense $expense): void
    {
        $this->assertReady();
        if ($expense->source_type) {
            throw new FinanceException("“{$expense->label}” is already linked to something else.");
        }
        $source = "{$kind}:".$expense->spent_at->format('Y-m');
        if ($this->auto->find($source, $item->id)) {
            throw new FinanceException("{$item->name} already has an expense for ".$expense->spent_at->format('F Y').'.');
        }

        $expense->update(['source_type' => $source, 'source_id' => $item->id]);
        ActivityLog::log('finance_expense_linked', 'FinanceExpense', $expense->id, ['source' => $source, 'source_id' => $item->id]);
    }

    private function costNote($cost, ?string $currency): string
    {
        $currency = strtoupper((string) ($currency ?: 'TZS'));

        return $currency === 'TZS' ? '' : ' · paid '.rtrim(rtrim(number_format((float) $cost, 2), '0'), '.')." {$currency}";
    }

    private function assertReady(): void
    {
        if (! FinanceExpense::typesReady()) {
            throw new FinanceException('Apply database change 0035 (Admin → Database) first.');
        }
    }
}
