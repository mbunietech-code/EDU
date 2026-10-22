<?php

namespace Tests\Feature;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinanceMoneyAccount;
use App\Models\FinanceMoneyTransfer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FinanceExpense::forgetTypesReady();
        \App\Services\Finance\MoneyAccountService::forgetReady();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function seedBooks(): array
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-22 12:00:00'));
        $product = Product::factory()->create(['name' => 'Grammarly', 'status' => 'published']);
        $order = Order::factory()->create(['product_id' => $product->id, 'status' => 'confirmed', 'amount' => 50000]);
        Payment::create(['order_id' => $order->id, 'user_id' => $order->user_id, 'amount' => 50000, 'payment_method' => 'alipay',
            'transaction_reference' => 'A1', 'status' => 'approved', 'reviewed_at' => '2026-10-02 09:00:00']);

        $alipay = FinanceMoneyAccount::create(['name' => 'Alipay', 'type' => 'wallet', 'payment_methods' => ['alipay']]);
        $crdb = FinanceMoneyAccount::create(['name' => 'CRDB', 'type' => 'bank']);

        $loan = FinanceCapitalEntry::create(['label' => 'Grammarly', 'amount' => 250000, 'source' => 'Dr. Mussa Awadhi', 'is_loan' => true, 'money_account_id' => $crdb->id]);
        FinanceLoanRepayment::create(['finance_capital_entry_id' => $loan->id, 'amount' => 60000, 'paid_at' => '2026-10-10', 'money_account_id' => $crdb->id]);
        FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-10-05', 'expense_type' => 'internet']); // no account
        FinanceMoneyTransfer::create(['from_account_id' => $alipay->id, 'to_account_id' => $crdb->id, 'amount' => 20000, 'transferred_at' => '2026-10-06']);

        return compact('alipay', 'crdb');
    }

    public function test_every_movement_has_both_sides_and_the_trial_balance_balances(): void
    {
        $this->seedBooks();
        $ledger = app(LedgerService::class);
        $journal = $ledger->journal();

        $this->assertCount(5, $journal);
        $sale = $journal->firstWhere('kind', 'sale');
        $this->assertSame(['Cash: Alipay', 'Sales: Grammarly'], [$sale['dr'], $sale['cr']]);
        $repay = $journal->firstWhere('kind', 'repayment');
        $this->assertSame(['Loan: Mussa Awadhi', 'Cash: CRDB'], [$repay['dr'], $repay['cr']], 'Lender titles are dropped so one person is one loan account.');
        $expense = $journal->firstWhere('kind', 'expense');
        $this->assertSame(['Expense: Internet / bundles', LedgerService::UNASSIGNED_CASH], [$expense['dr'], $expense['cr']]);

        $trial = $ledger->trialBalance($journal)->keyBy('account');
        $this->assertEquals($trial->sum('dr'), $trial->sum('cr'));
        $this->assertEquals(30000, $trial['Cash: Alipay']['balance']);          // 50k sale − 20k transfer
        $this->assertEquals(210000, $trial['Cash: CRDB']['balance']);           // 250k loan + 20k − 60k
        $this->assertEquals(-190000, $trial['Loan: Mussa Awadhi']['balance']);  // still owed (credit)
        $this->assertEquals(-10000, $trial[LedgerService::UNASSIGNED_CASH]['balance']);
    }

    public function test_ledger_page_and_month_filter(): void
    {
        $this->seedBooks();
        $page = fn (array $q = []) => $this->actingAs($this->admin)->withSession(['finance_unlocked' => true])->get(route('admin.finance.ledger.index', $q));

        $page()->assertOk()->assertSee('Trial balance')->assertSee('Balanced')->assertSee('Sales: Grammarly')->assertSee('October 2026');
        $page(['month' => '2026-09'])->assertOk()->assertSee('Nothing recorded for this period.');
        $page(['month' => 'bad'])->assertOk()->assertSee('Sales: Grammarly');

        $this->actingAs($this->admin)->withSession(['finance_unlocked' => true])
            ->get(route('admin.finance.money.show', FinanceMoneyAccount::firstWhere('name', 'CRDB')))
            ->assertOk()->assertSee('Debit (in)')->assertSee('Credit (out)');
    }

    public function test_app_api_ledger(): void
    {
        $this->seedBooks();
        Setting::set('finance_pin', '123456');
        $token = $this->admin->createToken('app')->plainTextToken;
        $api = fn (string $m, string $uri, array $d = []) => $this->withHeader('Authorization', 'Bearer '.$token)->json($m, '/api/admin/finance'.$uri, $d);
        $api('POST', '/unlock', ['pin' => '123456'])->assertOk();

        // All five movements are in October 2026 (the loan was recorded "today", 22 Oct).
        $api('GET', '/money/ledger?month=2026-10')->assertOk()
            ->assertJsonPath('data.month', '2026-10')
            ->assertJsonCount(5, 'data.journal');
        $api('GET', '/money/ledger?month=2026-09')->assertOk()->assertJsonCount(0, 'data.journal');
    }
}
