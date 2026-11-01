<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinanceMoneyAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\BudgetService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\LoanService;
use App\Services\Finance\MoneyAccountService;
use App\Services\FinanceOverviewService;
use App\Support\FinanceStart;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Finance counting from a start date: earlier records are kept, untouched and
 * read-only, under "Unorganised data"; customer payments are only read.
 */
class FinanceStartDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FinanceMoneyAccount $alipay;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        FinanceStart::forget();
        MoneyAccountService::forgetReady();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    protected function tearDown(): void
    {
        FinanceStart::forget();
        MoneyAccountService::forgetReady();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    private function sale(Product $product, float $amount, string $at): Payment
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'amount' => $amount, 'product_id' => $product->id]);
        $payment = Payment::create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'amount' => $amount, 'payment_method' => 'alipay',
            'transaction_reference' => 'TX'.$order->id, 'status' => 'approved', 'reviewed_at' => $at,
        ]);
        $payment->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        return $payment;
    }

    /** October records (old) and November ones (the books), all through Alipay. */
    private function seedBooks(): void
    {
        $product = Product::factory()->create(['name' => 'Grammarly']);
        $this->alipay = FinanceMoneyAccount::create(['name' => 'Alipay', 'type' => 'wallet', 'payment_methods' => ['alipay']]);

        $this->sale($product, 100000, '2026-10-31 20:59:00'); // 23:59 on 31 Oct in Tanzania: old
        $this->sale($product, 40000, '2026-10-31 21:30:00');  // 00:30 on 1 Nov in Tanzania: the books

        FinanceExpense::create(['label' => 'Old bundle', 'amount' => 30000, 'spent_at' => '2026-10-31', 'money_account_id' => $this->alipay->id]);
        FinanceExpense::create(['label' => 'New bundle', 'amount' => 5000, 'spent_at' => '2026-11-02', 'money_account_id' => $this->alipay->id]);

        $loan = FinanceCapitalEntry::create(['label' => 'Grammarly', 'product_id' => $product->id, 'amount' => 100000, 'source' => 'Rhoda',
            'is_loan' => true, 'received_at' => '2026-10-10', 'money_account_id' => $this->alipay->id]);
        FinanceLoanRepayment::create(['finance_capital_entry_id' => $loan->id, 'amount' => 30000, 'paid_at' => '2026-10-20', 'money_account_id' => $this->alipay->id]);
        FinanceLoanRepayment::create(['finance_capital_entry_id' => $loan->id, 'amount' => 20000, 'paid_at' => '2026-11-03', 'money_account_id' => $this->alipay->id]);
    }

    private function start(?float $alipayCounted = 150000): void
    {
        FinanceStart::save('2026-11-01', $alipayCounted === null ? [] : [$this->alipay->id => $alipayCounted]);
    }

    public function test_without_a_start_date_every_record_counts(): void
    {
        $this->seedBooks();
        $totals = app(FinanceOverviewService::class)->totals(app(FinanceOverviewService::class)->rows());

        $this->assertSame(140000.0, (float) $totals['income']);
        $this->assertSame(35000.0, (float) $totals['expenses']);
        $old = app(FinanceOverviewService::class)->totals(app(FinanceOverviewService::class)->rows(null, true));
        $this->assertSame([0, 0, 0, 0, 0], array_map(fn ($v) => (int) $v, array_values($old)));
        $this->finance()->get(route('admin.finance.unorganised.index'))->assertOk()->assertSee('Set a start date');
    }

    public function test_the_books_count_from_the_start_and_the_old_records_stay_apart(): void
    {
        $this->seedBooks();
        $paymentsBefore = Payment::orderBy('id')->get(['id', 'amount', 'status', 'created_at', 'updated_at'])->toArray();
        $this->start();

        $overview = app(FinanceOverviewService::class);
        $now = $overview->totals($overview->rows());
        $this->assertSame(40000.0, (float) $now['income']);
        $this->assertSame(5000.0, (float) $now['expenses']);
        $this->assertSame(0.0, (float) $now['capital']);
        $this->assertSame(20000.0, (float) $now['repaid']);

        $old = $overview->totals($overview->rows(null, true));
        $this->assertSame(100000.0, (float) $old['income']);
        $this->assertSame(30000.0, (float) $old['expenses']);
        $this->assertSame(100000.0, (float) $old['capital']);
        $this->assertSame(30000.0, (float) $old['repaid']);

        // Customer payments are only read: nothing about them changed.
        $this->assertSame($paymentsBefore, Payment::orderBy('id')->get(['id', 'amount', 'status', 'created_at', 'updated_at'])->toArray());
        $this->assertSame(4, FinanceExpense::count() + FinanceCapitalEntry::count() + FinanceLoanRepayment::count() - 1);

        $this->finance()->get(route('admin.finance.dashboard'))->assertOk()
            ->assertSee('Counting from 01 Nov 2026')->assertSee('Since 01 Nov 2026');
        $this->finance()->get(route('admin.finance.expenses.index'))->assertOk()
            ->assertSee('New bundle')->assertDontSee('Old bundle');
        $this->assertNotContains('2026-10', app(BudgetService::class)->months());
    }

    public function test_an_account_starts_with_the_money_counted_on_the_start_day(): void
    {
        $this->seedBooks();
        $money = app(MoneyAccountService::class);
        $this->assertSame(140000.0, $money->balanceBefore($this->alipay, FinanceStart::date() ?? Carbon::parse('2026-10-31 21:00:00')));

        $this->start(150000);
        $now = $money->balances()->first();
        $this->assertSame(150000.0, $now['opening']);
        $this->assertTrue($now['counted']);
        $this->assertSame(165000.0, $now['balance']); // 150,000 + 40,000 − 5,000 − 20,000
        $this->assertCount(3, $money->statement($this->alipay));

        // Before the start: the account's own opening and what the records left.
        $old = $money->balances(true, true)->first();
        $this->assertSame(140000.0, $old['balance']);

        $this->finance()->get(route('admin.finance.money.show', $this->alipay->id))->assertOk()
            ->assertSee('Counted')->assertSee('165,000');

        // Not counted: it starts with what the earlier records leave in it.
        $this->start(null);
        $this->assertSame(140000.0, $money->balances()->first()['opening']);
        $this->assertSame(155000.0, $money->balances()->first()['balance']);
    }

    public function test_loans_still_owed_are_brought_forward(): void
    {
        $this->seedBooks();
        $this->start();

        $loans = app(LoanService::class);
        $this->assertSame(['brought' => 70000.0, 'borrowed' => 0.0, 'repaid' => 20000.0, 'outstanding' => 50000.0], $loans->totals());
        $this->assertCount(1, $loans->broughtForward());

        $this->finance()->get(route('admin.finance.capital.index'))->assertOk()
            ->assertSee('Loans brought forward')->assertSee('Owed then TZS 70,000');
    }

    public function test_the_ledger_opens_on_the_start_day_and_balances(): void
    {
        $this->seedBooks();
        $this->start();
        $ledger = app(LedgerService::class);

        $journal = $ledger->journal();
        $opening = $journal->where('kind', 'opening')->values();
        $this->assertCount(2, $opening);
        $this->assertSame(['Cash: Alipay', LedgerService::OPENING, 150000.0], [$opening[0]['dr'], $opening[0]['cr'], $opening[0]['amount']]);
        $this->assertSame([LedgerService::OPENING, 'Loan: Rhoda', 70000.0], [$opening[1]['dr'], $opening[1]['cr'], $opening[1]['amount']]);
        $this->assertCount(5, $journal);

        $trial = $ledger->trialBalance($journal);
        $this->assertEquals(round($trial->sum('dr'), 2), round($trial->sum('cr'), 2));
        $this->assertSame(165000.0, $trial->firstWhere('account', 'Cash: Alipay')['balance']);
        $this->assertSame(-50000.0, $trial->firstWhere('account', 'Loan: Rhoda')['balance']);

        $old = $ledger->journal(null, null, true);
        $this->assertCount(4, $old);
        $this->assertCount(0, $old->where('kind', 'opening'));
    }

    public function test_records_before_the_start_are_read_only(): void
    {
        $this->seedBooks();
        $this->start();
        $old = FinanceExpense::where('label', 'Old bundle')->first();

        $this->finance()->get(route('admin.finance.expenses.edit', $old))
            ->assertRedirect(route('admin.finance.unorganised.index'));
        $this->finance()->put(route('admin.finance.expenses.update', $old), ['label' => 'Changed', 'amount' => 1, 'spent_at' => '2026-11-02'])
            ->assertSessionHasErrors('spent_at');
        $this->finance()->delete(route('admin.finance.expenses.destroy', $old), ['reason' => 'test'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('Old bundle', $old->fresh()->label);

        // New records cannot be dated into the old period.
        $this->finance()->post(route('admin.finance.expenses.store'), ['label' => 'Late', 'amount' => 1000, 'spent_at' => '2026-10-30'])
            ->assertSessionHasErrors('spent_at');
        $this->finance()->post(route('admin.finance.expenses.store'), ['label' => 'Fine', 'amount' => 1000, 'spent_at' => '2026-11-04'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, FinanceExpense::where('label', 'Late')->count());
        $this->assertSame(1, FinanceExpense::where('label', 'Fine')->count());

        // A November expense cannot be moved back into October either.
        $new = FinanceExpense::where('label', 'New bundle')->first();
        $this->finance()->put(route('admin.finance.expenses.update', $new), ['label' => 'New bundle', 'amount' => 5000, 'spent_at' => '2026-10-15'])
            ->assertSessionHasErrors('spent_at');

        // The app is held to the same rule.
        Setting::set('finance_pin', '123456');
        $token = $this->admin->createToken('app')->plainTextToken;
        $api = fn (string $m, string $uri, array $d = []) => $this->withHeader('Authorization', 'Bearer '.$token)->json($m, '/api/admin/finance'.$uri, $d);
        $api('POST', '/unlock', ['pin' => '123456'])->assertOk();
        $api('PUT', '/expenses/'.$old->id, ['label' => 'Changed', 'amount' => 1, 'spent_at' => '2026-11-02'])->assertStatus(422);
        $api('GET', '/expenses')->assertOk()->assertJsonMissing(['label' => 'Old bundle']);
        $this->assertSame('Old bundle', $old->fresh()->label);
    }

    public function test_the_unorganised_page_shows_every_old_record_read_only(): void
    {
        $this->seedBooks();
        $this->start();

        $this->finance()->get(route('admin.finance.unorganised.index'))->assertOk()
            ->assertSee('Unorganised data')->assertSee('Grammarly')->assertSee('140,000')->assertSee('+10,000');
        $this->finance()->get(route('admin.finance.unorganised.index', ['tab' => 'expenses']))->assertOk()
            ->assertSee('Old bundle')->assertDontSee('New bundle')->assertDontSee(route('admin.finance.expenses.edit', 1));
        $this->finance()->get(route('admin.finance.unorganised.index', ['tab' => 'expenses', 'month' => '2026-10']))->assertOk()->assertSee('Old bundle');
        foreach (['sales', 'capital', 'repayments', 'transfers', 'given', 'ledger'] as $tab) {
            $this->finance()->get(route('admin.finance.unorganised.index', ['tab' => $tab]))->assertOk();
        }
        $csv = $this->finance()->get(route('admin.finance.unorganised.csv'));
        $csv->assertOk();
        $this->assertStringContainsString('Old bundle', $csv->streamedContent());
        $this->assertStringNotContainsString('New bundle', $csv->streamedContent());
    }

    public function test_the_start_date_and_counts_are_set_from_the_page(): void
    {
        $this->seedBooks();

        $this->finance()->get(route('admin.finance.start.edit', ['date' => '2026-11-01']))->assertOk()
            ->assertSee('Alipay')->assertSee('140,000');

        $this->finance()->put(route('admin.finance.start.update'), ['start_date' => '2026-11-01', 'balances' => [$this->alipay->id => '150000']])
            ->assertRedirect(route('admin.finance.dashboard'));
        $this->assertSame('2026-11-01', FinanceStart::date()->toDateString());
        $this->assertSame(150000.0, FinanceStart::balanceFor($this->alipay->id));
        $this->assertTrue(ActivityLog::where('action', 'finance_start_set')->exists());

        $this->finance()->put(route('admin.finance.start.update'), ['start_date' => '2026-12-01'])->assertSessionHasErrors('start_date');

        $this->finance()->delete(route('admin.finance.start.destroy'))->assertRedirect(route('admin.finance.dashboard'));
        $this->assertNull(FinanceStart::date());
        $this->assertSame(140000.0, (float) app(FinanceOverviewService::class)->totals(app(FinanceOverviewService::class)->rows())['income']);
    }

    public function test_the_pages_are_behind_the_finance_lock(): void
    {
        $this->actingAs($this->admin)->get(route('admin.finance.unorganised.index'))->assertRedirect();
        $this->actingAs($this->admin)->put(route('admin.finance.start.update'), ['start_date' => '2026-11-01'])->assertRedirect();
        $this->assertNull(FinanceStart::date());
    }
}
