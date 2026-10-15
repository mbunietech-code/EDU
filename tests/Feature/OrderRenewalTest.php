<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderRenewalTest extends TestCase
{
    use RefreshDatabase, CreatesOrderContext;

    /** A confirmed order whose subscription ends in $daysLeft days. */
    private function confirmedOrder(int $daysLeft, string $subStatus = 'active'): array
    {
        $context = $this->createOrderContext(['accounts' => 2]);
        $context['order']->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $account = Account::where('product_id', $context['product']->id)->first();
        $account->update(['status' => $subStatus === 'expired' ? 'available' : 'assigned']);

        $context['subscription'] = Subscription::create([
            'user_id' => $context['user']->id,
            'product_id' => $context['product']->id,
            'plan_id' => $context['plan']->id,
            'account_id' => $account->id,
            'order_id' => $context['order']->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'expiry_date' => now()->addDays($daysLeft)->toDateString(),
            'status' => $subStatus,
        ]);
        $context['account'] = $account;

        return $context;
    }

    private function approveRenewal(Order $renewal): void
    {
        app(PaymentApprovalService::class)->approve($this->createPendingPayment($renewal, $renewal->user));
    }

    public function test_renew_is_offered_only_near_the_end(): void
    {
        $far = $this->confirmedOrder(20);
        $this->assertFalse($far['order']->canBeRenewed());

        $near = $this->confirmedOrder(3);
        $this->assertTrue($near['order']->canBeRenewed());

        $this->actingAs($near['user'])->get(route('user.orders.show', $near['order']))
            ->assertOk()->assertSee('Renew now');
        $this->actingAs($far['user'])->get(route('user.orders.show', $far['order']))
            ->assertOk()->assertDontSee('Renew now');
    }

    public function test_renew_creates_a_pending_order_linked_to_the_old_one(): void
    {
        ['order' => $order, 'user' => $user, 'plan' => $plan] = $this->confirmedOrder(2);

        $response = $this->actingAs($user)->post(route('user.orders.renew', $order));

        $renewal = Order::where('renews_order_id', $order->id)->firstOrFail();
        $response->assertRedirect(route('user.payments.create', $renewal));
        $this->assertSame('pending', $renewal->status);
        $this->assertSame($plan->id, $renewal->plan_id);
        $this->assertEquals($plan->price, $renewal->amount);

        // A second click goes to the same unpaid renewal, not a duplicate.
        $this->actingAs($user)->post(route('user.orders.renew', $order))
            ->assertRedirect(route('user.payments.create', $renewal));
        $this->assertSame(1, Order::where('renews_order_id', $order->id)->count());
    }

    public function test_paying_the_renewal_extends_the_same_subscription_from_its_end_date(): void
    {
        ['order' => $order, 'user' => $user, 'subscription' => $sub, 'account' => $account] = $this->confirmedOrder(2);
        $oldEnd = $sub->expiry_date->copy();

        $this->actingAs($user)->post(route('user.orders.renew', $order));
        $renewal = Order::where('renews_order_id', $order->id)->firstOrFail();
        $this->approveRenewal($renewal);

        $sub->refresh();
        $this->assertSame(1, Subscription::count());
        $this->assertSame($renewal->id, $sub->order_id);
        $this->assertSame($account->id, $sub->account_id);
        $this->assertSame('active', $sub->status);
        $this->assertSame($oldEnd->addDays(30)->toDateString(), $sub->expiry_date->toDateString());

        $this->assertSame('renewed', $order->fresh()->displayStatus());
        $this->assertSame('active', $renewal->fresh()->displayStatus());
        $this->assertFalse($order->fresh()->canBeRenewed());
    }

    public function test_renewing_after_expiry_starts_today_on_the_same_account_when_free(): void
    {
        ['order' => $order, 'user' => $user, 'account' => $account] = $this->confirmedOrder(-5, 'expired');

        $this->assertTrue($order->canBeRenewed());
        $this->actingAs($user)->post(route('user.orders.renew', $order));
        $renewal = Order::where('renews_order_id', $order->id)->firstOrFail();
        $this->approveRenewal($renewal);

        $new = Subscription::where('order_id', $renewal->id)->firstOrFail();
        $this->assertSame($account->id, $new->account_id);
        $this->assertSame(now()->addDays(30)->toDateString(), $new->expiry_date->toDateString());
        $this->assertSame('assigned', $account->fresh()->status);
    }

    public function test_customer_can_pick_another_active_plan_but_not_one_from_another_product(): void
    {
        ['order' => $order, 'user' => $user, 'product' => $product] = $this->confirmedOrder(1);
        $yearly = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'duration_days' => 365, 'price' => 400000]);
        $foreign = Plan::factory()->create(['product_id' => Product::factory()->create(['status' => 'published'])->id, 'status' => 'active', 'price' => 10]);

        $this->actingAs($user)->post(route('user.orders.renew', $order), ['plan_id' => $foreign->id])
            ->assertSessionHas('error');
        $this->assertSame(0, Order::where('renews_order_id', $order->id)->count());

        $this->actingAs($user)->post(route('user.orders.renew', $order), ['plan_id' => $yearly->id]);
        $renewal = Order::where('renews_order_id', $order->id)->firstOrFail();
        $this->assertSame($yearly->id, $renewal->plan_id);
        $this->assertEquals(400000, $renewal->amount);
    }

    public function test_others_cannot_renew_and_unrenewable_orders_are_refused(): void
    {
        ['order' => $order] = $this->confirmedOrder(1);
        $this->actingAs(User::factory()->create())->post(route('user.orders.renew', $order))->assertForbidden();

        ['order' => $far, 'user' => $owner] = $this->confirmedOrder(25);
        $this->actingAs($owner)->post(route('user.orders.renew', $far))->assertSessionHas('error');
        $this->assertSame(0, Order::whereNotNull('renews_order_id')->count());
    }

    public function test_vpn_order_can_be_renewed_and_the_renewal_is_activated(): void
    {
        config(['services.mvpn.url' => 'https://vpn.test', 'services.mvpn.partner_secret' => 'test-partner-secret-0123456789abcdef0123']);
        Http::fake([
            'vpn.test/api/partner/activations' => Http::response([
                'duplicate' => false,
                'subscription' => ['plan' => 'm1', 'status' => 'active', 'expires_at' => now()->addDays(33)->toIso8601String()],
            ], 201),
        ]);

        $product = Product::factory()->create(['status' => 'published', 'type' => 'vpn']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'duration_days' => 30, 'price' => 15000, 'mvpn_plan_code' => 'm1']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'plan_id' => $plan->id,
            'amount' => $plan->price, 'status' => 'confirmed', 'confirmed_at' => now()->subDays(27),
        ]);
        $order->forceFill(['vpn_activated_at' => now()->subDays(27), 'vpn_expires_at' => now()->addDays(3)])->save();

        $this->assertTrue($order->fresh()->canBeRenewed());
        $this->actingAs($user)->post(route('user.orders.renew', $order));
        $renewal = Order::where('renews_order_id', $order->id)->firstOrFail();
        $this->approveRenewal($renewal);

        $renewal->refresh();
        $this->assertNotNull($renewal->vpn_activated_at);
        $this->assertSame(0, Subscription::count());
        $this->assertSame('renewed', $order->fresh()->displayStatus());
        Http::assertSent(fn ($r) => $r['reference'] === 'eduhub-order-'.$renewal->id);
    }

    public function test_app_api_lists_can_renew_and_renews(): void
    {
        ['order' => $order, 'user' => $user, 'plan' => $plan] = $this->confirmedOrder(2);

        $this->actingAs($user, 'sanctum')->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.can_renew', true);

        $first = $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$order->id}/renew")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.renews_order_id', $order->id)
            ->json('data.id');

        // Again: the unpaid renewal comes back, no duplicate.
        $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$order->id}/renew")
            ->assertOk()
            ->assertJsonPath('data.id', $first);
        $this->assertSame(1, Order::where('renews_order_id', $order->id)->count());
    }

    public function test_app_api_refuses_early_and_foreign_renewals(): void
    {
        ['order' => $far, 'user' => $user] = $this->confirmedOrder(20);

        $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$far->id}/renew")
            ->assertStatus(422);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/orders/{$far->id}/renew")
            ->assertNotFound();
        $this->assertSame(0, Order::whereNotNull('renews_order_id')->count());
    }
}
