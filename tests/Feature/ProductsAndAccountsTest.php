<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\CredentialService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Plans inside the product form, quick / bulk account add, one sidebar entry. */
class ProductsAndAccountsTest extends TestCase
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

    private function decrypt(?string $value): ?string
    {
        return app(CredentialService::class)->decryptValue($value);
    }

    public function test_product_is_created_with_its_plans_in_one_save(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'name' => 'Claude Pro',
            'slug' => 'claude-pro',
            'price' => 60000,
            'status' => 'published',
            'type' => 'subscription',
            'plans' => [
                ['name' => '1 month', 'price' => '60000', 'duration_type' => 'days', 'duration_days' => '30', 'status' => 'active'],
                ['name' => 'Lifetime', 'price' => '500000', 'duration_type' => 'lifetime', 'duration_days' => '30', 'status' => 'active'],
                ['name' => '', 'price' => '', 'duration_type' => 'days', 'duration_days' => '30', 'status' => 'active'],
            ],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::where('slug', 'claude-pro')->firstOrFail();
        $plans = $product->plans()->orderBy('sort_order')->get();

        $this->assertCount(2, $plans);
        $this->assertSame(['1 month', 'Lifetime'], $plans->pluck('name')->all());
        $this->assertSame(30, $plans[0]->duration_days);
        $this->assertSame('lifetime', $plans[1]->duration_type);
        $this->assertNull($plans[1]->duration_days);
    }

    public function test_editing_a_product_updates_adds_and_removes_plans(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $keep = Plan::create(['product_id' => $product->id, 'name' => '1 month', 'price' => 50000, 'duration_type' => 'days', 'duration_days' => 30, 'status' => 'active']);
        $unused = Plan::create(['product_id' => $product->id, 'name' => 'Old trial', 'price' => 1000, 'duration_type' => 'days', 'duration_days' => 3, 'status' => 'active']);
        $sold = Plan::create(['product_id' => $product->id, 'name' => 'Sold plan', 'price' => 90000, 'duration_type' => 'days', 'duration_days' => 60, 'status' => 'active']);
        Order::factory()->create(['user_id' => User::factory()->create()->id, 'product_id' => $product->id, 'plan_id' => $sold->id, 'amount' => 90000, 'status' => 'confirmed']);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => 50000,
            'status' => 'published',
            'type' => 'subscription',
            'plans' => [
                ['id' => $keep->id, 'name' => '1 month', 'price' => '55000', 'duration_type' => 'days', 'duration_days' => '30', 'status' => 'active'],
                ['id' => $unused->id, 'name' => 'Old trial', 'price' => '1000', 'duration_type' => 'days', 'duration_days' => '3', 'status' => 'active', 'remove' => '1'],
                ['id' => $sold->id, 'name' => 'Sold plan', 'price' => '90000', 'duration_type' => 'days', 'duration_days' => '60', 'status' => 'active', 'remove' => '1'],
                ['name' => '3 months', 'price' => '150000', 'duration_type' => 'days', 'duration_days' => '90', 'status' => 'active'],
            ],
        ])->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'switched off'));

        $this->assertEquals(55000, (float) $keep->fresh()->price);
        $this->assertNull(Plan::find($unused->id));
        // Bought before: kept for history, just no longer on sale.
        $this->assertSame('inactive', $sold->fresh()->status);
        $this->assertTrue($product->plans()->where('name', '3 months')->where('duration_days', 90)->exists());

        // A plan of another product cannot be edited through this form.
        $other = Plan::create(['product_id' => Product::create(['name' => 'X', 'slug' => 'x', 'type' => 'subscription', 'status' => 'published', 'price' => 1])->id,
            'name' => 'Other', 'price' => 1, 'duration_type' => 'days', 'duration_days' => 1, 'status' => 'active']);
        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name, 'slug' => $product->slug, 'price' => 50000, 'status' => 'published', 'type' => 'subscription',
            'plans' => [['id' => $other->id, 'name' => 'Hijacked', 'price' => '1', 'duration_type' => 'days', 'duration_days' => '1', 'remove' => '1']],
        ]);
        $this->assertSame('Other', $other->fresh()->name);
    }

    public function test_product_form_shows_its_plans(): void
    {
        $product = $this->product();
        Plan::create(['product_id' => $product->id, 'name' => 'Six months', 'price' => 250000, 'duration_type' => 'days', 'duration_days' => 180, 'status' => 'active']);

        $this->actingAs($this->admin())->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Add plan')
            ->assertSee('Six months');

        $this->actingAs($this->admin())->get(route('admin.products.create'))->assertOk()->assertSee('Add plan');
    }

    public function test_one_account_is_added_with_just_email_and_password(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())->get(route('admin.accounts.create'))->assertOk()->assertSee('Several at once');

        $this->actingAs($this->admin())->post(route('admin.accounts.store'), [
            'product_id' => $product->id,
            'login_email' => 'seat1@example.com',
            'login_password' => 'Secret-123',
        ])->assertRedirect(route('admin.accounts.index'));

        $account = Account::firstOrFail();
        $this->assertSame('seat1@example.com', $account->name);
        $this->assertSame('available', $account->status);
        $this->assertStringNotContainsString('Secret-123', (string) $account->credentials);
        $this->assertSame("email: seat1@example.com\npassword: Secret-123", $this->decrypt($account->credentials));
    }

    public function test_several_accounts_are_added_at_once_and_duplicates_skipped(): void
    {
        $product = $this->product();
        Account::create(['product_id' => $product->id, 'name' => 'seat2@example.com', 'status' => 'available']);

        $this->actingAs($this->admin())->post(route('admin.accounts.bulk'), [
            'product_id' => $product->id,
            'lines' => "seat1@example.com:Pass1\nseat2@example.com:Pass2\n\nseat3@example.com, Pass 3\nseat4@example.com\tPass4",
            'expires_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success', '3 account(s) added. 1 already in the list were skipped.');

        $this->assertSame(4, Account::where('product_id', $product->id)->count());
        $seat3 = Account::where('name', 'seat3@example.com')->firstOrFail();
        $this->assertSame("email: seat3@example.com\npassword: Pass 3", $this->decrypt($seat3->credentials));
        $this->assertNotNull($seat3->expires_at);

        // A line without a password: nothing is added, the line is named, the passwords are not echoed back.
        $this->actingAs($this->admin())->post(route('admin.accounts.bulk'), [
            'product_id' => $product->id,
            'lines' => "seat9@example.com:Pass9\nlonely@example.com",
        ])->assertSessionHasErrors(['lines' => 'Line 2 needs both a login and a password.'])
            ->assertSessionMissing('_old_input.lines');

        $this->assertFalse(Account::where('name', 'seat9@example.com')->exists());
    }

    public function test_restricted_admin_cannot_bulk_add(): void
    {
        $staff = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['accounts.view']]);

        $this->actingAs($staff)->post(route('admin.accounts.bulk'), [
            'product_id' => $this->product()->id,
            'lines' => 'a@b.c:x',
        ])->assertForbidden();
    }

    public function test_waiting_account_gets_its_login_from_the_edit_form(): void
    {
        $product = $this->product();
        $account = Account::create([
            'product_id' => $product->id,
            'name' => 'Waiting for login · ORD-1',
            'status' => 'assigned',
            'metadata' => ['awaiting_setup' => true, 'order_id' => 1],
        ]);

        $this->actingAs($this->admin())->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('Waiting for login details (1)');

        $this->actingAs($this->admin())->get(route('admin.accounts.edit', $account))
            ->assertOk()
            ->assertSee('has paid and is waiting for this login');

        $this->actingAs($this->admin())->put(route('admin.accounts.update', $account), [
            'product_id' => $product->id,
            'name' => $account->name,
            'status' => 'assigned',
            'login_email' => 'seat7@example.com',
            'login_password' => 'Pw7',
        ])->assertRedirect();

        $account->refresh();
        $this->assertSame('seat7@example.com', $account->name);
        $this->assertFalse($account->isAwaitingSetup());
        $this->assertSame("email: seat7@example.com\npassword: Pw7", $this->decrypt($account->credentials));
        $this->assertSame(0, Account::awaitingSetup()->count());
    }

    public function test_sidebar_has_one_products_entry_and_a_system_group(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.accounts.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Products &amp; accounts', $html);
        $this->assertStringContainsString("openSection === 'system'", $html);
        $this->assertStringNotContainsString('label="Plans"', $html);

        // Tabs inside the section.
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*Accounts/', $html);
        $this->actingAs($this->admin())->get(route('admin.accounts.plans'))->assertOk()->assertSee('AI plans we bought');
    }
}
