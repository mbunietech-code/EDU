<?php

namespace Tests\Feature;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\MonthlyReportService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceMonthlyReportTest extends TestCase
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

    private function october(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
        $chatgpt = Product::factory()->create(['name' => 'ChatGPT', 'status' => 'published']);
        $grammarly = Product::factory()->create(['name' => 'Grammarly', 'status' => 'published']);

        foreach ([[$chatgpt, 120000, 'Pending', 'pending'], [$chatgpt, 80000, 'A', 'approved'], [$grammarly, 300000, 'B', 'approved']] as [$p, $amt, $ref, $status]) {
            $order = Order::factory()->create(['product_id' => $p->id, 'status' => 'confirmed', 'amount' => $amt]);
            Payment::create(['order_id' => $order->id, 'user_id' => $order->user_id, 'amount' => $amt, 'payment_method' => 'yas',
                'transaction_reference' => $ref, 'status' => $status, 'reviewed_at' => '2026-10-05 10:00:00']);
        }

        FinanceExpense::create(['label' => 'ChatGPT', 'amount' => 90000, 'spent_at' => '2026-10-04', 'expense_type' => 'product_accounts', 'product_id' => $chatgpt->id]);
        FinanceExpense::create(['label' => 'Wifi', 'amount' => 70000, 'spent_at' => '2026-10-03', 'expense_type' => 'internet']);
        FinanceExpense::create(['label' => 'Old one', 'amount' => 15500, 'spent_at' => '2026-10-20', 'category' => 'Miscellaneous']);
        FinanceCapitalEntry::create(['label' => 'ChatGPT', 'amount' => 90000, 'source' => 'Mussa', 'is_loan' => true]);
    }

    public function test_profit_and_loss_for_a_month(): void
    {
        $this->october();
        $p = app(MonthlyReportService::class)->pnl(Carbon::parse('2026-10-01'));

        $this->assertSame(['Grammarly' => 300000.0, 'ChatGPT' => 80000.0], $p['income'], 'Only approved payments.');
        $this->assertSame(['ChatGPT' => 90000.0], $p['cogs']);
        $this->assertEquals(290000, $p['gross']);
        $this->assertSame(['Internet / bundles' => 70000.0, 'Miscellaneous' => 15500.0], $p['opex']);
        $this->assertEquals(204500, $p['net']);
        $this->assertEquals(90000, $p['cash']['loans_in']);
        $this->assertEquals(-10000, $p['products']['ChatGPT']['profit'], 'ChatGPT cost more than it sold this month.');
    }

    public function test_report_page_checklist_and_csv(): void
    {
        $this->october();
        $get = fn (string $route, array $q = []) => $this->actingAs($this->admin)->withSession(['finance_unlocked' => true])->get(route($route, $q));

        $get('admin.finance.report.index', ['month' => '2026-10'])->assertOk()
            ->assertSee('Monthly report · October 2026')->assertSee('Net profit')->assertSee('204,500')
            ->assertSee('Month-end checklist')->assertSee('1 waiting.')->assertSee('1 without a type this month.')
            ->assertSee('Not prepared yet.')->assertSee('Profit per product')->assertSee('−10,000');

        $csv = $get('admin.finance.report.csv', ['month' => '2026-10']);
        $csv->assertOk();
        $this->assertStringContainsString('"Net profit",,204500', $csv->streamedContent());
    }

    public function test_app_api_report(): void
    {
        $this->october();
        Setting::set('finance_pin', '123456');
        $token = $this->admin->createToken('app')->plainTextToken;
        $api = fn (string $m, string $uri, array $d = []) => $this->withHeader('Authorization', 'Bearer '.$token)->json($m, '/api/admin/finance'.$uri, $d);
        $api('POST', '/unlock', ['pin' => '123456'])->assertOk();

        $api('GET', '/money/report?month=2026-10')->assertOk()
            ->assertJsonPath('data.pnl.net', 204500)
            ->assertJsonPath('data.checklist.0.done', false);
    }
}
