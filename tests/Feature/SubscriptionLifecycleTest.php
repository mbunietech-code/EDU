<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveSubscription(Carbon $start, Carbon $end): Subscription
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'duration_days' => 30]);
        $account = Account::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'confirmed',
        ]);

        return (new SubscriptionService(
            app(\App\Actions\Subscriptions\CreateSubscription::class),
            app(\App\Actions\Subscriptions\ExtendSubscription::class),
            app(\App\Actions\Subscriptions\ExpireSubscription::class),
            app(\App\Services\AccountAssignmentService::class),
        ))->createFromOrderWithDates($order, $start, $end);
    }

    public function test_subscription_is_created_active_and_account_assigned(): void
    {
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addDays(30);

        $subscription = $this->createActiveSubscription($start, $end);

        $this->assertEquals('active', $subscription->status);
        $this->assertNotNull($subscription->account_id);
        $this->assertEquals('assigned', $subscription->account->status);
        $this->assertEquals($end->toDateString(), $subscription->expiry_date->toDateString());
    }

    public function test_expire_releases_the_account(): void
    {
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addDays(30);
        $subscription = $this->createActiveSubscription($start, $end);
        $accountId = $subscription->account_id;

        $service = app(SubscriptionService::class);
        $service->expireSubscriptionAndReleaseAccount($subscription);

        $this->assertEquals('expired', $subscription->fresh()->status);
        $this->assertEquals('available', Account::find($accountId)->status);
    }

    public function test_extend_adds_days_to_expiry(): void
    {
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addDays(30);
        $subscription = $this->createActiveSubscription($start, $end);

        $service = app(SubscriptionService::class);
        $service->extend($subscription, 14);

        $expected = $end->copy()->addDays(14)->toDateString();
        $this->assertEquals($expected, $subscription->fresh()->expiry_date->toDateString());
    }

    public function test_expiring_soon_scope_and_is_expiring_soon(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        Account::factory()->create(['product_id' => $product->id, 'status' => 'available']);

        $active = Subscription::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
            'expiry_date' => Carbon::now()->addDays(2),
        ]);
        Subscription::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
            'expiry_date' => Carbon::now()->addDays(20),
        ]);
        Subscription::factory()->create([
            'product_id' => $product->id,
            'status' => 'expiring_soon',
            'expiry_date' => Carbon::now()->addDays(1),
        ]);

        $this->assertTrue($active->isExpiringSoon(3));
        $this->assertEquals(1, Subscription::expiringSoon()->count());
    }

    public function test_lifetime_plan_gets_far_future_expiry(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create([
            'product_id' => $product->id,
            'duration_type' => 'lifetime',
            'duration_days' => null,
        ]);
        $account = Account::factory()->create(['product_id' => $product->id, 'status' => 'available']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'confirmed',
        ]);

        $service = app(SubscriptionService::class);
        $subscription = $service->createFromOrder($order);

        $this->assertEquals('active', $subscription->status);
        $this->assertEquals('2099-12-31', $subscription->expiry_date->toDateString());
        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isExpiringSoon(30));
    }

    public function test_expired_scope(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        Subscription::factory()->create([
            'product_id' => $product->id,
            'status' => 'expired',
            'expiry_date' => Carbon::now()->subDays(5),
        ]);
        Subscription::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
            'expiry_date' => Carbon::now()->addDays(10),
        ]);

        $this->assertEquals(1, Subscription::expired()->count());
    }
}
