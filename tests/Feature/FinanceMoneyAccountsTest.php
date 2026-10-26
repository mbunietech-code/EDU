<?php

namespace Tests\Feature;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinanceMoneyAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\MoneyAccountService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceMoneyAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        MoneyAccountService::forgetReady();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    protected function tearDown(): void
    {
        MoneyAccountService::forgetReady();
        parent::tearDown();
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    private function sale(string $method, float $amount, string $date): Payment
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'amount' => $amount]);

        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $amount,
            'payment_method' => $method,
            'transaction_reference' => 'TX'.$order->id,
            'status' => 'approved',
            'reviewed_at' => $date,
        ]);
    }

    private function account(array $attrs = []): FinanceMoneyAccount
    {
        return FinanceMoneyAccount::create($attrs + ['name' => 'Alipay', 'type' => 'wallet', 'payment_methods' => ['alipay']]);
    }

    public function test_balance_and_statement_follow_every_kind_of_movement(): void
    {
        $this->sale('alipay', 100000, '2026-10-01 10:00:00');
        $this->sale('alipay', 50000, '2026-10-03 10:00:00');
        $this->sale('yas', 70000, '2026-10-02 10:00:00'); // other method: not in Alipay

        $alipay = $this->account();
        $crdb = $this->account(['name' => 'CRDB', 'type' => 'bank', 'payment_methods' => []]);

        $loan = FinanceCapitalEntry::create(['label' => 'ChatGPT', 'amount' => 90000, 'source' => 'Mussa', 'is_loan' => true, 'money_account_id' => $alipay->id]);
        FinanceExpense::create(['label' => 'ChatGPT', 'amount' => 90000, 'spent_at' => '2026-10-04', 'money_account_id' => $alipay->id]);
        FinanceLoanRepayment::create(['finance_capital_entry_id' => $loan->id, 'amount' => 40000, 'paid_at' => '2026-10-05', 'money_account_id' => $alipay->id]);

        $this->finance()->post(route('admin.finance.money.transfer'), [
            'from_account_id' => $alipay->id, 'to_account_id' => $crdb->id, 'amount' => 30000, 'transferred_at' => '2026-10-06',
        ])->assertSessionHas('success');

        // 100k + 50k sales + 90k loan − 90k expense − 40k repaid − 30k transfer = 80k
        $service = app(MoneyAccountService::class);
        $rows = $service->statement($alipay->fresh());
        $this->assertSame(80000.0, $rows->last()['balance']);
        $this->assertEqualsCanonicalizing(['income', 'income', 'loan', 'expense', 'repayment', 'transfer_out'], $rows->pluck('kind')->all());
        // Oldest first: the 1 Oct sale opens the statement with its own amount.
        $this->assertSame(100000.0, $rows->first()['balance']);
        $this->assertSame(30000.0, $service->statement($crdb->fresh())->last()['balance']);

        // The YAS sale lands in no account yet, so it shows as not assigned.
        $this->finance()->get(route('admin.finance.money.index'))->assertOk()
            ->assertSee('TZS 80,000')->assertSee('TZS 30,000')
            ->assertSee('Not assigned yet');

        $this->finance()->get(route('admin.finance.money.show', $alipay))->assertOk()
            ->assertSee('Opening balance')->assertSee('Loan repaid: ChatGPT')->assertSee('To CRDB')->assertSee('TZS 80,000');
    }

    public function test_opening_balance_date_leaves_out_earlier_movements(): void
    {
        $this->sale('alipay', 100000, '2026-09-20 10:00:00'); // before opening: already in the opening balance
        $this->sale('alipay', 25000, '2026-10-02 10:00:00');
        $alipay = $this->account(['opening_balance' => 400000, 'opening_date' => '2026-10-01']);

        $rows = app(MoneyAccountService::class)->statement($alipay);
        $this->assertCount(1, $rows);
        $this->assertSame(425000.0, $rows->last()['balance']);
    }

    public function test_a_payment_method_lands_in_one_account_only(): void
    {
        $this->account();

        $this->finance()->post(route('admin.finance.money.store'), [
            'name' => 'Alipay 2', 'type' => 'wallet', 'payment_methods' => ['alipay'],
        ])->assertSessionHas('error');

        $this->assertSame(1, FinanceMoneyAccount::count());
    }

    public function test_existing_records_stay_untouched_until_assigned_and_assigning_never_moves_assigned_ones(): void
    {
        $old = FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-09-01']);
        $oldCapital = FinanceCapitalEntry::create(['label' => 'Grammarly', 'amount' => 250000, 'source' => 'Rhoda', 'is_loan' => true]);
        $this->sale('wechat_pay', 60000, '2026-10-01 10:00:00');

        $yas = $this->account(['name' => 'MIX by YAS', 'type' => 'mobile_money', 'payment_methods' => ['yas']]);
        $crdb = $this->account(['name' => 'CRDB', 'type' => 'bank', 'payment_methods' => []]);
        $already = FinanceExpense::create(['label' => 'Wifi', 'amount' => 70000, 'spent_at' => '2026-10-01', 'money_account_id' => $crdb->id]);

        // Nothing changed by creating accounts.
        $this->assertNull($old->fresh()->money_account_id);
        $this->assertEquals(10000, $old->fresh()->amount);

        $u = app(MoneyAccountService::class)->unassigned();
        $this->assertSame(60000.0, $u['totals']['payments']);
        $this->assertSame(250000.0 + 60000 - 10000, $u['totals']['net']);

        $this->finance()->get(route('admin.finance.money.index'))->assertOk()
            ->assertSee('Not assigned yet')->assertSee('Wechat Pay')->assertSee('Bundle'); // no payment_methods row in tests: label from the code

        $this->finance()->post(route('admin.finance.money.assign'), [
            'money_account_id' => $yas->id,
            'expense' => [$old->id, $already->id],
            'capital' => [$oldCapital->id],
        ])->assertSessionHas('success', '2 records now go through MIX by YAS.');

        $this->assertSame($yas->id, $old->fresh()->money_account_id);
        $this->assertSame($yas->id, $oldCapital->fresh()->money_account_id);
        $this->assertSame($crdb->id, $already->fresh()->money_account_id, 'An already assigned record never moves.');
        $this->assertEquals(10000, $old->fresh()->amount);
    }

    public function test_expense_capital_and_repayment_forms_save_the_account(): void
    {
        $crdb = $this->account(['name' => 'CRDB', 'type' => 'bank', 'payment_methods' => []]);

        $this->finance()->get(route('admin.finance.expenses.index'))->assertOk()->assertSee('Paid from');
        $this->finance()->post(route('admin.finance.expenses.store'), [
            'label' => 'Umeme', 'amount' => 30000, 'spent_at' => '2026-10-10', 'money_account_id' => $crdb->id,
        ])->assertSessionHas('success');
        $this->assertSame($crdb->id, FinanceExpense::firstWhere('label', 'Umeme')->money_account_id);

        $this->finance()->post(route('admin.finance.capital.store'), [
            'label' => 'Kampuni', 'amount' => 100000, 'source' => 'Owner', 'is_loan' => 1, 'money_account_id' => $crdb->id,
        ])->assertSessionHas('success');
        $loan = FinanceCapitalEntry::firstWhere('label', 'Kampuni');
        $this->assertSame($crdb->id, $loan->money_account_id);

        $this->finance()->get(route('admin.finance.capital.repayments.index', $loan))->assertOk()->assertSee('Paid from');
        $this->finance()->post(route('admin.finance.capital.repayments.store', $loan), [
            'amount' => 20000, 'paid_at' => now()->toDateString(), 'money_account_id' => $crdb->id,
        ])->assertSessionHas('success');
        $this->assertSame($crdb->id, $loan->repayments()->first()->money_account_id);

        $this->finance()->get(route('admin.finance.dashboard'))->assertOk()
            ->assertSee('Where the money is')->assertSee('TZS 50,000'); // 100k − 30k − 20k
    }

    public function test_new_entries_start_on_the_account_used_last(): void
    {
        $alipay = $this->account();
        $yas = $this->account(['name' => 'MIX by YAS', 'type' => 'mobile_money', 'payment_methods' => ['yas']]);
        $selected = fn ($id) => '<option value="'.$id.'" selected';

        // Nothing used yet: the form leaves the choice open.
        $this->finance()->get(route('admin.finance.expenses.index'))->assertOk()
            ->assertDontSee($selected($alipay->id), false)->assertDontSee($selected($yas->id), false);

        FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-10-10', 'money_account_id' => $yas->id]);

        // The next expense, capital entry or repayment starts on YAS.
        $this->finance()->get(route('admin.finance.expenses.index'))->assertOk()->assertSee($selected($yas->id), false);
        $this->finance()->get(route('admin.finance.capital.index'))->assertOk()->assertSee($selected($yas->id), false);

        // Editing an unassigned record does not quietly assign it.
        $old = FinanceExpense::create(['label' => 'Old', 'amount' => 5000, 'spent_at' => '2026-09-01']);
        $this->finance()->get(route('admin.finance.expenses.edit', $old))->assertOk()->assertDontSee($selected($yas->id), false);
    }

    public function test_everything_works_before_the_database_change_is_applied(): void
    {
        $expense = FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-09-01']);

        Schema::table('finance_expenses', fn ($t) => $t->dropConstrainedForeignId('money_account_id'));
        Schema::table('finance_capital_entries', fn ($t) => $t->dropConstrainedForeignId('money_account_id'));
        Schema::table('finance_loan_repayments', fn ($t) => $t->dropConstrainedForeignId('money_account_id'));
        Schema::drop('finance_money_transfers');
        Schema::drop('finance_money_accounts');
        MoneyAccountService::forgetReady();

        $this->finance()->get(route('admin.finance.money.index'))->assertOk()->assertSee('0034_2026_10_22_finance_money_accounts.sql');
        $this->finance()->get(route('admin.finance.money.show', 1))->assertRedirect(route('admin.finance.money.index'));
        $this->finance()->get(route('admin.finance.dashboard'))->assertOk()->assertSee('Set up money accounts');
        $this->finance()->get(route('admin.finance.expenses.index'))->assertOk()->assertDontSee('Paid from');
        $this->finance()->get(route('admin.finance.expenses.edit', $expense))->assertOk();
        $this->finance()->get(route('admin.finance.capital.index'))->assertOk();

        $this->finance()->post(route('admin.finance.expenses.store'), [
            'label' => 'Umeme', 'amount' => 30000, 'spent_at' => '2026-10-10', 'money_account_id' => 5,
        ])->assertSessionHas('success');
        $this->assertSame(2, FinanceExpense::count());
    }

    public function test_app_api_lists_accounts_statement_and_assigns(): void
    {
        Setting::set('finance_pin', '123456');
        $token = $this->admin->createToken('app')->plainTextToken;
        $api = fn (string $m, string $uri, array $d = []) => $this->withHeader('Authorization', 'Bearer '.$token)->json($m, '/api/admin/finance'.$uri, $d);
        $api('POST', '/unlock', ['pin' => '123456'])->assertOk();

        $this->sale('alipay', 100000, '2026-10-01 10:00:00');
        $expense = FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-10-02']);

        $id = $api('POST', '/money', ['name' => 'Alipay', 'type' => 'wallet', 'payment_methods' => ['alipay']])
            ->assertCreated()->json('data.id');

        $api('GET', '/money')->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.accounts.0.balance', 100000)
            ->assertJsonPath('data.unassigned.expenses.0.id', $expense->id);

        $api('POST', '/money/assign', ['money_account_id' => $id, 'expense' => [$expense->id]])->assertOk()->assertJsonPath('data.assigned', 1);

        $api('GET', "/money/{$id}")->assertOk()
            ->assertJsonPath('data.balance', 90000)
            ->assertJsonPath('data.statement.1.kind', 'expense');

        $api('GET', '/targets')->assertOk()->assertJsonPath('data.money_accounts.0.name', 'Alipay');
    }
}
