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

    /** Items bought before purchases recorded themselves (saved without model events). */
    private function items(): array
    {
        $product = Product::factory()->create(['name' => 'ChatGPT', 'status' => 'published']);
        $account = Account::withoutEvents(fn () => Account::factory()->create(['product_id' => $product->id, 'name' => 'chatgpt-07', 'cost' => 127.5, 'cost_currency' => 'CNY', 'purchased_at' => '2026-10-08', 'status' => 'assigned']));
        $vps = CompanyAsset::withoutEvents(fn () => CompanyAsset::create(['name' => 'VPS Hostinger', 'category' => 'server', 'billing_cycle' => 'monthly', 'cost' => 24000, 'currency' => 'TZS', 'purchased_at' => '2026-09-25']));

        return compact('product', 'account', 'vps');
    }

    public function test_a_new_purchase_and_a_renewal_record_themselves(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-27 12:00:00');
        $yas = FinanceMoneyAccount::create(['name' => 'MIX by YAS', 'type' => 'mobile_money']);
        FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-10-20', 'money_account_id' => $yas->id]);
        $product = Product::factory()->create(['name' => 'Claude Pro', 'status' => 'published']);

        // A new account with a foreign price: converted, on its purchase day, paid from the account used last.
        $account = Account::factory()->create(['product_id' => $product->id, 'name' => 'claude-01', 'cost' => 20, 'cost_currency' => 'USD', 'purchased_at' => '2026-10-25', 'status' => 'available']);
        $e = FinanceExpense::where('source_id', $account->id)->where('source_type', 'account:2026-10')->sole();
        $this->assertEquals(50000, $e->amount);
        $this->assertSame('2026-10-25', $e->spent_at->toDateString());
        $this->assertSame($yas->id, $e->money_account_id);
        $this->assertSame($product->id, $e->product_id);

        // Saving it again changes nothing; renewing (a new purchase date) records the new payment.
        $account->update(['description' => 'shared']);
        $this->assertSame(1, FinanceExpense::where('source_id', $account->id)->where('source_type', 'like', 'account:%')->count());

        $domain = CompanyAsset::create(['name' => 'mbuniehub.com', 'category' => 'domain', 'billing_cycle' => 'yearly', 'cost' => 35000, 'currency' => 'TZS', 'purchased_at' => '2025-11-01', 'expires_at' => '2026-11-01']);
        $this->assertSame(1, FinanceExpense::where('source_type', 'asset:2025-11')->count());
        $this->actingAs($this->admin)->post(route('admin.assets.renew', $domain))->assertSessionHas('success');
        $renewal = FinanceExpense::where('source_type', 'asset:2026-10')->where('source_id', $domain->id)->sole();
        $this->assertSame('hosting', $renewal->expense_type);
        $this->assertEquals(35000, $renewal->amount);

        // The page lists them as recorded, with nothing left to sort out.
        $this->finance()->get(route('admin.finance.purchases.index'))->assertOk()
            ->assertSee('claude-01')->assertSee('25 Oct 2026 · TZS 50,000')->assertDontSee('Not in Finance yet ·');

        // Nothing for a purchase still to come, or without a cost.
        CompanyAsset::create(['name' => 'Later', 'category' => 'server', 'billing_cycle' => 'monthly', 'cost' => 5000, 'currency' => 'TZS', 'purchased_at' => '2026-11-05']);
        CompanyAsset::create(['name' => 'Free', 'category' => 'software', 'billing_cycle' => 'monthly', 'cost' => 0, 'currency' => 'TZS', 'purchased_at' => '2026-10-01']);
        $this->assertSame(0, FinanceExpense::whereIn('label', ['Later', 'Free'])->count());
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_page_lists_items_with_a_cost_and_suggests_tzs(): void
    {
        $this->items();

        $this->finance()->get(route('admin.finance.purchases.index'))->assertOk()
            ->assertSee('chatgpt-07')->assertSee('127.5 CNY')->assertSee('≈ TZS 51,000', false)
            ->assertSee('VPS Hostinger')->assertSee('Not in Finance yet · 2')->assertSee('It is already in Expenses', false);
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

        $this->finance()->get(route('admin.finance.purchases.index'))->assertOk()->assertSee('08 Oct 2026 · TZS 51,000')->assertDontSee('Not in Finance yet ·');
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
