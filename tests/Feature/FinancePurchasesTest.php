<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CompanyAsset;
use App\Models\FinanceExpense;
use App\Models\FinanceMoneyAccount;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FinancePurchasesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FinanceExpense::forgetTypesReady();
        \App\Services\Finance\MoneyAccountService::forgetReady();
        Cache::put('currency_rates', ['USD' => 0.0004, 'CNY' => 0.0025]); // 1 USD = 2,500 TZS; 1 CNY = 400 TZS
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    private function items(): array
    {
        $product = Product::factory()->create(['name' => 'ChatGPT', 'status' => 'published']);
        $account = Account::factory()->create(['product_id' => $product->id, 'name' => 'chatgpt-07', 'cost' => 127.5, 'cost_currency' => 'CNY', 'purchased_at' => '2026-10-08', 'status' => 'assigned']);
        $vps = CompanyAsset::create(['name' => 'VPS Hostinger', 'category' => 'server', 'billing_cycle' => 'monthly', 'cost' => 24000, 'currency' => 'TZS', 'purchased_at' => '2026-09-25']);

        return compact('product', 'account', 'vps');
    }

    public function test_page_lists_items_with_a_cost_and_suggests_tzs(): void
    {
        $this->items();

        $this->finance()->get(route('admin.finance.purchases.index'))->assertOk()
            ->assertSee('chatgpt-07')->assertSee('127.5 CNY')->assertSee('≈ TZS 51,000 today', false)
            ->assertSee('VPS Hostinger')->assertSee('Not recorded in Finance yet.');
    }

    public function test_recording_a_payment_creates_one_expense_per_month(): void
    {
        ['account' => $account, 'vps' => $vps, 'product' => $product] = $this->items();
        $crdb = FinanceMoneyAccount::create(['name' => 'CRDB', 'type' => 'bank']);

        $this->finance()->post(route('admin.finance.purchases.record', ['account', $account->id]), [
            'amount' => 51000, 'spent_at' => '2026-10-08', 'money_account_id' => $crdb->id,
        ])->assertSessionHas('success');

        $e = FinanceExpense::sole();
        $this->assertSame('product_accounts', $e->expense_type);
        $this->assertSame('Cost of Goods Sold (COGS)', $e->category);
        $this->assertSame($product->id, $e->product_id, 'Counts against its product.');
        $this->assertSame('ChatGPT', $e->label);
        $this->assertSame($crdb->id, $e->money_account_id);
        $this->assertStringContainsString('127.5 CNY', $e->description);

        // Same month again is refused; next month is a new payment.
        $this->finance()->post(route('admin.finance.purchases.record', ['account', $account->id]), ['amount' => 51000, 'spent_at' => '2026-10-20'])
            ->assertSessionHas('error');
        $this->finance()->post(route('admin.finance.purchases.record', ['asset', $vps->id]), ['amount' => 24000, 'spent_at' => '2026-09-25'])
            ->assertSessionHas('success');
        $this->assertSame('hosting', FinanceExpense::where('label', 'VPS Hostinger')->value('expense_type'));
        $this->assertSame(2, FinanceExpense::count());

        $this->finance()->get(route('admin.finance.purchases.index'))->assertOk()->assertSee('Recorded:')->assertSee('Oct 2026');
    }

    public function test_linking_to_an_expense_typed_by_hand_never_doubles_it(): void
    {
        ['vps' => $vps] = $this->items();
        $typed = FinanceExpense::create(['label' => 'Hosting VPS', 'amount' => 24000, 'spent_at' => '2026-09-25']);

        $this->finance()->post(route('admin.finance.purchases.link', ['asset', $vps->id]), ['expense_id' => $typed->id])
            ->assertSessionHas('success');

        $typed->refresh();
        $this->assertSame('asset:2026-09', $typed->source_type);
        $this->assertSame($vps->id, $typed->source_id);
        $this->assertSame('Hosting VPS', $typed->label, 'Only the link changes.');

        $this->finance()->post(route('admin.finance.purchases.record', ['asset', $vps->id]), ['amount' => 24000, 'spent_at' => '2026-09-26'])
            ->assertSessionHas('error');
        $this->assertSame(1, FinanceExpense::count());
    }

    public function test_app_api_purchases(): void
    {
        ['account' => $account] = $this->items();
        Setting::set('finance_pin', '123456');
        $token = $this->admin->createToken('app')->plainTextToken;
        $api = fn (string $m, string $uri, array $d = []) => $this->withHeader('Authorization', 'Bearer '.$token)->json($m, '/api/admin/finance'.$uri, $d);
        $api('POST', '/unlock', ['pin' => '123456'])->assertOk();

        $api('GET', '/money/purchases')->assertOk()
            ->assertJsonPath('data.accounts.0.cost_tzs', 51000)
            ->assertJsonPath('data.assets.0.default_type', 'hosting');
        $api('POST', "/money/purchases/account/{$account->id}/record", ['amount' => 51000, 'spent_at' => '2026-10-08'])->assertCreated();
        $api('POST', "/money/purchases/account/{$account->id}/record", ['amount' => 51000, 'spent_at' => '2026-10-09'])->assertStatus(422);
    }
}
