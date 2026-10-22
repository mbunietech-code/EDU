<?php

namespace Tests\Feature;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\BudgetService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FinanceExpense::forgetTypesReady();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    private function october(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'amount' => 1000000]);
        Payment::create(['order_id' => $order->id, 'user_id' => $order->user_id, 'amount' => 1000000, 'payment_method' => 'yas',
            'transaction_reference' => 'Y1', 'status' => 'approved', 'reviewed_at' => '2026-10-03 10:00:00']);
        $september = Order::factory()->create(['status' => 'confirmed', 'amount' => 500000]);
        Payment::create(['order_id' => $september->id, 'user_id' => $september->user_id, 'amount' => 500000, 'payment_method' => 'yas',
            'transaction_reference' => 'Y0', 'status' => 'approved', 'reviewed_at' => '2026-09-20 10:00:00']);

        FinanceExpense::create(['label' => 'ChatGPT', 'amount' => 300000, 'spent_at' => '2026-10-04', 'expense_type' => 'product_accounts']);
        FinanceExpense::create(['label' => 'Salaries', 'amount' => 250000, 'spent_at' => '2026-10-25', 'expense_type' => 'salaries']); // over 20%
        FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-10-05', 'category' => 'Miscellaneous']); // untyped → category
        FinanceExpense::create(['label' => 'Mystery', 'amount' => 5000, 'spent_at' => '2026-10-06']); // no type, no category
        FinanceExpense::create(['label' => 'September', 'amount' => 99000, 'spent_at' => '2026-09-06', 'expense_type' => 'internet']);
        $loan = FinanceCapitalEntry::create(['label' => 'Grammarly', 'amount' => 250000, 'source' => 'Rhoda', 'is_loan' => true]);
        FinanceLoanRepayment::create(['finance_capital_entry_id' => $loan->id, 'amount' => 40000, 'paid_at' => '2026-10-10']);
    }

    public function test_month_splits_income_and_counts_spending_per_bucket(): void
    {
        $this->october();
        $r = app(BudgetService::class)->month(Carbon::parse('2026-10-01'));
        $rows = collect($r['rows'])->keyBy('key');

        $this->assertEquals(1000000, $r['income'], 'Only October income.');
        $this->assertEquals(350000, $rows['cogs']['budget']);
        $this->assertEquals(300000, $rows['cogs']['spent']);
        $this->assertEquals(-50000, $rows['people']['left'], '250k salaries on a 200k budget.');
        $this->assertEquals(10000, $rows['operations']['spent'], 'Untyped expense falls back to its category.');
        $this->assertEquals(40000, $rows['growth']['spent'], 'Loan repayments count in growth.');
        $this->assertEquals(5000, $r['unbudgeted']);
        $this->assertEquals(605000, $r['spent']);
    }

    public function test_budget_page_and_percentages_must_add_up_to_100(): void
    {
        $this->october();

        $this->finance()->get(route('admin.finance.budget.index', ['month' => '2026-10']))->assertOk()
            ->assertSee('Budget · October 2026')->assertSee('Product costs (COGS)')->assertSee('Not in any bucket')
            ->assertSee('Capital by product')->assertSee('Grammarly');

        $percents = ['cogs' => 40, 'people' => 20, 'operations' => 15, 'tax' => 10, 'growth' => 10, 'reserve' => 10];
        $this->finance()->put(route('admin.finance.budget.update'), ['percents' => $percents])->assertSessionHas('error');

        $percents['cogs'] = 35;
        $percents['people'] = 25;
        $percents['reserve'] = 5;
        $this->finance()->put(route('admin.finance.budget.update'), ['percents' => $percents])->assertSessionHas('success');

        $r = app(BudgetService::class)->month(Carbon::parse('2026-10-01'));
        $this->assertEquals(250000, collect($r['rows'])->firstWhere('key', 'people')['budget']);
    }

    public function test_app_api_budget(): void
    {
        $this->october();
        Setting::set('finance_pin', '123456');
        $token = $this->admin->createToken('app')->plainTextToken;
        $api = fn (string $m, string $uri, array $d = []) => $this->withHeader('Authorization', 'Bearer '.$token)->json($m, '/api/admin/finance'.$uri, $d);
        $api('POST', '/unlock', ['pin' => '123456'])->assertOk();

        $api('GET', '/money/budget?month=2026-10')->assertOk()
            ->assertJsonPath('data.month', '2026-10')
            ->assertJsonPath('data.income', 1000000)
            ->assertJsonPath('data.rows.0.key', 'cogs');
        $api('PUT', '/money/budget', ['percents' => ['cogs' => 100, 'people' => 0, 'operations' => 0, 'tax' => 0, 'growth' => 0, 'reserve' => 0]])->assertOk();
        $api('PUT', '/money/budget', ['percents' => ['cogs' => 1, 'people' => 0, 'operations' => 0, 'tax' => 0, 'growth' => 0, 'reserve' => 0]])->assertStatus(422);
    }
}
