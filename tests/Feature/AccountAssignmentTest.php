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
}
