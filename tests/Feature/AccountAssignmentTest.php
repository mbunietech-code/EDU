<?php

namespace Tests\Feature;

use App\Actions\Accounts\AssignAccount;
use App\Models\Account;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_an_account_marks_it_assigned(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $account = Account::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $subscription = Subscription::factory()->create(['product_id' => $product->id, 'account_id' => null]);

        $assigned = (new AssignAccount())->execute($subscription);

        $this->assertSame($account->id, $assigned->id);
        $this->assertEquals('assigned', $assigned->fresh()->status);
    }

    public function test_assigning_without_available_account_throws(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        Account::factory()->create(['product_id' => $product->id, 'status' => 'assigned']);
        $subscription = Subscription::factory()->create(['product_id' => $product->id, 'account_id' => null]);

        $this->expectException(\RuntimeException::class);

        (new AssignAccount())->execute($subscription);
    }

    public function test_no_two_subscriptions_share_the_same_account(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $account = Account::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $subscriptionA = Subscription::factory()->create(['product_id' => $product->id, 'account_id' => null]);
        $subscriptionB = Subscription::factory()->create(['product_id' => $product->id, 'account_id' => null]);

        $assignedA = (new AssignAccount())->execute($subscriptionA);
        $this->assertSame($account->id, $assignedA->id);

        $this->expectException(\RuntimeException::class);
        (new AssignAccount())->execute($subscriptionB);
    }

    public function test_accounts_whose_own_plan_ended_are_never_handed_out(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        Account::factory()->create(['product_id' => $product->id, 'status' => 'available', 'expires_at' => now()->subDay()]);
        $good = Account::factory()->create(['product_id' => $product->id, 'status' => 'available', 'expires_at' => now()->addMonth()]);
        $open = Account::factory()->create(['product_id' => $product->id, 'status' => 'available', 'expires_at' => null]);

        $service = app(\App\Services\AccountAssignmentService::class);
        $this->assertSame($good->id, $service->findAvailableAccount($product->id)->id);

        $good->update(['status' => 'assigned']);
        $this->assertSame($open->id, $service->findAvailableAccount($product->id)->id, 'No end date recorded still counts as running.');

        $open->update(['status' => 'assigned']);
        $this->assertNull($service->findAvailableAccount($product->id), 'Only the ended one is left: nothing to hand out.');

        $this->expectException(\RuntimeException::class);
        (new AssignAccount())->execute(Subscription::factory()->create(['product_id' => $product->id, 'account_id' => null]));
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => \App\Support\Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    public function test_account_page_shows_the_current_user_apart_from_previous_ones(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $account = Account::factory()->create(['product_id' => $product->id, 'status' => 'assigned']);
        $old = User::factory()->create(['name' => 'Francois Old']);
        $new = User::factory()->create(['name' => 'Anunu New']);
        Subscription::factory()->create(['account_id' => $account->id, 'product_id' => $product->id, 'user_id' => $old->id, 'status' => 'expired', 'expiry_date' => now()->subDays(20)]);
        Subscription::factory()->create(['account_id' => $account->id, 'product_id' => $product->id, 'user_id' => $new->id, 'status' => 'active', 'expiry_date' => now()->addDays(30)]);

        $html = $this->actingAs($this->superAdmin())->get(route('admin.accounts.show', $account))
            ->assertOk()
            ->assertSee('Current user')
            ->assertSee('Previous users (1)')
            ->assertSee('Change its password')
            ->getContent();

        // The current holder is listed before the history.
        $this->assertLessThan(strpos($html, 'Previous users'), strpos($html, 'Anunu New'));
        $this->assertGreaterThan(strpos($html, 'Previous users'), strpos($html, 'Francois Old'));
    }

    public function test_accounts_list_shows_the_current_user_and_finds_accounts_by_customer(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $mine = Account::factory()->create(['product_id' => $product->id, 'name' => 'chatgpt-07', 'status' => 'assigned']);
        Account::factory()->create(['product_id' => $product->id, 'name' => 'chatgpt-99', 'status' => 'available']);
        $old = User::factory()->create(['name' => 'Francois Old', 'email' => 'francois@example.com']);
        $new = User::factory()->create(['name' => 'Anunu New', 'email' => 'anunu@example.com']);
        Subscription::factory()->create(['account_id' => $mine->id, 'product_id' => $product->id, 'user_id' => $old->id, 'status' => 'expired', 'expiry_date' => now()->subDays(20)]);
        Subscription::factory()->create(['account_id' => $mine->id, 'product_id' => $product->id, 'user_id' => $new->id, 'status' => 'active', 'expiry_date' => now()->addDays(30)]);

        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('admin.accounts.index'))
            ->assertOk()->assertSee('Current user')->assertSee('Anunu New')->assertDontSee('Francois Old');

        $this->actingAs($admin)->get(route('admin.accounts.index', ['search' => 'anunu@example']))
            ->assertOk()->assertSee('chatgpt-07')->assertDontSee('chatgpt-99');
        $this->actingAs($admin)->get(route('admin.accounts.index', ['search' => 'Francois']))
            ->assertOk()->assertSee('chatgpt-07')->assertDontSee('chatgpt-99');
        $this->actingAs($admin)->get(route('admin.accounts.index', ['search' => 'chatgpt-99']))
            ->assertOk()->assertSee('chatgpt-99')->assertDontSee('chatgpt-07');
    }
}
