<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** "AI plans we bought": purchase and end dates on shared accounts. */
class AccountPlansTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function product(): Product
    {
        return Product::create(['name' => 'ChatGPT Plus', 'slug' => 'chatgpt', 'type' => 'subscription', 'status' => 'published', 'price' => 50000]);
    }

    public function test_admin_records_plan_dates_and_sees_them_on_the_plans_page(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'product_id' => $product->id,
            'name' => 'chatgpt-team-1@mbuniehub.com',
            'status' => 'available',
            'plan_name' => 'ChatGPT Plus - monthly',
            'purchased_at' => now()->subDays(25)->toDateString(),
            'expires_at' => now()->addDays(5)->toDateString(),
            'cost' => '20',
            'cost_currency' => 'USD',
            'auto_renew' => '1',
        ])->assertRedirect();

        $account = Account::firstOrFail();
        $this->assertSame('ChatGPT Plus - monthly', $account->plan_name);
        $this->assertTrue($account->auto_renew);
        $this->assertSame(5, $account->planDaysLeft());
        $this->assertSame('expiring', $account->planState());

        Account::create(['product_id' => $product->id, 'name' => 'old', 'status' => 'available', 'expires_at' => now()->subDays(3)]);
        Account::create(['product_id' => $product->id, 'name' => 'no-dates', 'status' => 'available']);

        $this->get(route('admin.accounts.plans'))->assertOk()
            ->assertSee('ChatGPT Plus - monthly')
            ->assertSee('5 days left')
            ->assertSee('Ended 3 days ago')
            ->assertSee('No end date')
            ->assertSee('USD 20.00');

        $this->get(route('admin.accounts.plans', ['filter' => 'expired']))->assertOk()
            ->assertSee('Ended 3 days ago')->assertDontSee('5 days left');

        // The end date may not be before the purchase date.
        $this->put(route('admin.accounts.update', $account), [
            'product_id' => $product->id, 'name' => $account->name, 'status' => 'available',
            'purchased_at' => '2026-10-10', 'expires_at' => '2026-10-01',
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_app_api_lists_plans_soonest_first(): void
    {
        Sanctum::actingAs($this->admin());
        $product = $this->product();
        Account::create(['product_id' => $product->id, 'name' => 'later', 'status' => 'assigned', 'expires_at' => now()->addDays(30)]);
        Account::create(['product_id' => $product->id, 'name' => 'soon', 'status' => 'available', 'expires_at' => now()->addDays(2)]);
        Account::create(['product_id' => $product->id, 'name' => 'archived', 'status' => 'archived', 'expires_at' => now()->addDay()]);

        $this->getJson('/api/admin/accounts/plans')->assertOk()
            ->assertJsonPath('data.0.name', 'soon')
            ->assertJsonPath('data.0.plan_state', 'expiring')
            ->assertJsonPath('data.0.days_left', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.expiring', 1);

        $id = $this->postJson('/api/admin/accounts', [
            'product_id' => $product->id, 'name' => 'new', 'status' => 'available',
            'plan_name' => 'Claude Pro', 'purchased_at' => '2026-10-01', 'expires_at' => '2026-11-01', 'auto_renew' => null,
        ])->assertCreated()->json('data.id');
        $this->assertFalse(Account::find($id)->auto_renew);

        $this->getJson('/api/admin/accounts/plans?filter=active')->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_view_permission_is_needed(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => ['orders.view']]))
            ->get(route('admin.accounts.plans'))->assertForbidden();
    }
}
