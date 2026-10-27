<?php

namespace Tests\Feature;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The Finance overview filtered by day, week, month, year, all time or a custom range. */
class FinanceOverviewPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 12:00:00');
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    /** A customer payment made at $date (server time, UTC) and approved two days later. */
    private function sale(Product $product, float $amount, string $date): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'amount' => $amount, 'product_id' => $product->id]);
        $payment = Payment::create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'amount' => $amount, 'payment_method' => 'alipay',
            'transaction_reference' => 'TX'.$order->id, 'status' => 'approved',
            'reviewed_at' => Carbon::parse($date)->addDays(2),
        ]);
        $payment->forceFill(['created_at' => $date])->save();
    }

    private function seedMoney(): Product
    {
        $product = Product::factory()->create(['name' => 'Grammarly']);
        $this->sale($product, 50000, '2026-10-27 09:00:00');   // today
        $this->sale($product, 20000, '2026-10-21 09:00:00');   // last week, this month
        $this->sale($product, 11000, '2026-09-10 09:00:00');   // last month, this year
        $this->sale($product, 7000, '2025-12-31 09:00:00');    // last year

        FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-10-27']);
        FinanceExpense::create(['label' => 'Domain', 'amount' => 150000, 'spent_at' => '2026-08-22']);

        // Received on 23 Aug, typed into the system only on 2 Oct.
        $loan = FinanceCapitalEntry::create(['label' => 'Grammarly', 'product_id' => $product->id, 'amount' => 250000, 'source' => 'Rhoda', 'is_loan' => true, 'received_at' => '2026-08-23']);
        $loan->forceFill(['created_at' => '2026-10-02 10:00:00'])->save();
        FinanceLoanRepayment::create(['finance_capital_entry_id' => $loan->id, 'amount' => 30000, 'paid_at' => '2026-10-26']);

        return $product;
    }

    public function test_each_period_counts_only_what_happened_in_it(): void
    {
        $this->seedMoney();
        $totals = fn ($query) => $this->finance()->get(route('admin.finance.dashboard', $query))->assertOk()->viewData('totals');

        $all = $totals([]);
        $this->assertEquals(88000, $all['income']);
        $this->assertEquals(250000, $all['capital']);
        $this->assertEquals(160000, $all['expenses']);
        $this->assertEquals(30000, $all['repaid']);
        $this->assertEquals(250000 + 88000 - 160000 - 30000, $all['balance']);

        $day = $totals(['period' => 'day']);
        $this->assertEquals(50000, $day['income']);
        $this->assertEquals(10000, $day['expenses']);
        $this->assertEquals(0, $day['repaid']);

        // Monday 26 Oct – Sunday 1 Nov.
        $week = $totals(['period' => 'week']);
        $this->assertEquals(50000, $week['income']);
        $this->assertEquals(30000, $week['repaid']);

        $month = $totals(['period' => 'month']);
        $this->assertEquals(70000, $month['income']);
        $this->assertEquals(0, $month['capital']); // received in August, though recorded in October
        $this->assertEquals(50000 + 20000 - 10000 - 30000, $month['balance']);

        $this->assertEquals(81000, $totals(['period' => 'year'])['income']);
        $this->assertEquals(250000, $totals(['period' => 'year'])['capital']);

        // Stepping back: the month before, the year before.
        $this->assertEquals(11000, $totals(['period' => 'month', 'date' => '2026-09-15'])['income']);
        $this->assertEquals(7000, $totals(['period' => 'year', 'date' => '2025-06-01'])['income']);

        // Custom range, given back to front.
        $custom = $totals(['period' => 'custom', 'from' => '2026-10-21', 'to' => '2026-09-01']);
        $this->assertEquals(31000, $custom['income']);
    }

    public function test_days_follow_tanzania_time_and_the_day_the_customer_paid(): void
    {
        $product = Product::factory()->create(['name' => 'ChatGPT']);
        // 22:30 UTC on 26 Oct is 01:30 on 27 Oct in Dar es Salaam; approved two days later.
        $this->sale($product, 10000, '2026-10-26 22:30:00');
        // 20:00 UTC on 26 Oct is 23:00 on 26 Oct in Dar es Salaam.
        $this->sale($product, 6000, '2026-10-26 20:00:00');

        $day = fn ($date) => $this->finance()->get(route('admin.finance.dashboard', ['period' => 'day', 'date' => $date]))->viewData('totals')['income'];
        $this->assertEquals(10000, $day('2026-10-27'));
        $this->assertEquals(6000, $day('2026-10-26'));
        // Not on the approval day.
        $this->assertEquals(0, $day('2026-10-28'));
    }

    public function test_capital_is_entered_with_the_date_it_was_received(): void
    {
        $this->finance()->get(route('admin.finance.capital.index'))->assertOk()
            ->assertSee('Date received')->assertSee('value="2026-10-27"', false);

        $this->finance()->post(route('admin.finance.capital.store'), [
            'label' => 'Mkopo', 'amount' => 90000, 'source' => 'Mussa', 'is_loan' => 1, 'received_at' => '2026-10-19',
        ])->assertSessionHasNoErrors();
        $entry = FinanceCapitalEntry::firstWhere('label', 'Mkopo');
        $this->assertSame('2026-10-19', $entry->received_at->toDateString());

        // Not in the future; editing corrects the date of an older entry.
        $this->finance()->post(route('admin.finance.capital.store'), [
            'label' => 'Later', 'amount' => 1, 'source' => 'X', 'received_at' => '2026-11-30',
        ])->assertSessionHasErrors('received_at');
        $this->finance()->put(route('admin.finance.capital.update', $entry), [
            'label' => 'Mkopo', 'amount' => 90000, 'source' => 'Mussa', 'is_loan' => 1, 'received_at' => '2026-10-15',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-15', $entry->fresh()->received_at->toDateString());
    }

    public function test_the_page_shows_the_period_and_its_navigation(): void
    {
        $this->seedMoney();

        $this->finance()->get(route('admin.finance.dashboard', ['period' => 'month']))
            ->assertOk()
            ->assertSee('October 2026')
            ->assertSee('Net for the period')
            ->assertSee('period=month&amp;date=2026-09-27', false);

        // An empty period says so instead of listing every product at zero.
        $this->finance()->get(route('admin.finance.dashboard', ['period' => 'day', 'date' => '2026-01-05']))
            ->assertOk()
            ->assertSee('Nothing recorded in this period');

        // A bad period falls back to all time.
        $this->finance()->get(route('admin.finance.dashboard', ['period' => 'decade']))->assertOk()->assertSee('Total Income');
    }

    public function test_the_app_api_takes_the_same_period(): void
    {
        $this->seedMoney();
        $token = $this->admin->createToken('t');
        \Illuminate\Support\Facades\Cache::put('finance-unlock:token:'.$token->accessToken->id, true, 600);

        $this->withToken($token->plainTextToken)->getJson('/api/admin/finance/overview?period=month')
            ->assertOk()
            ->assertJsonPath('data.period.key', 'month')
            ->assertJsonPath('data.period.from', '2026-10-01')
            ->assertJsonPath('data.totals.income', 70000);
    }
}
