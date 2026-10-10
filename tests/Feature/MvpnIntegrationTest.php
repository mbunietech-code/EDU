<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\MvpnClient;
use App\Services\PaymentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MvpnIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-partner-secret-0123456789abcdef0123';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mvpn.url' => 'https://vpn.test',
            'services.mvpn.partner_secret' => self::SECRET,
        ]);
    }

    /** @return array{order: Order, payment: Payment, user: User, plan: Plan} */
    private function vpnOrder(?string $code = 'm1'): array
    {
        $product = Product::factory()->create(['status' => 'published', 'type' => 'vpn']);
        $plan = Plan::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
            'duration_days' => 30,
            'price' => 15000,
            'mvpn_plan_code' => $code,
        ]);
        $user = User::factory()->create(['email' => 'buyer@example.com', 'email_verified_at' => now()]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => 'pending',
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $order->amount,
            'payment_method' => 'clickpesa',
            'transaction_reference' => 'CP-'.$order->id,
            'status' => 'pending',
        ]);

        return compact('order', 'payment', 'user', 'plan');
    }

    private function fakeVpnOk(): void
    {
        Http::fake([
            'vpn.test/api/partner/activations' => Http::response([
                'duplicate' => false,
                'subscription' => ['plan' => 'm1', 'status' => 'active', 'expires_at' => '2026-11-10T10:00:00+00:00'],
            ], 201),
        ]);
    }

    public function test_approved_vpn_order_is_activated_with_a_valid_signature(): void
    {
        $this->fakeVpnOk();
        ['order' => $order, 'payment' => $payment, 'user' => $user] = $this->vpnOrder();

        app(PaymentApprovalService::class)->approve($payment);

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->vpn_activated_at);
        $this->assertSame('2026-11-10', $order->vpn_expires_at->toDateString());
        // Never treated as an AI-account subscription.
        $this->assertNull($order->subscription);

        Http::assertSent(function (Request $r) use ($order, $user) {
            $expected = MvpnClient::sign(self::SECRET, (int) $r->header('X-Mvpn-Timestamp')[0], 'POST', '/api/partner/activations', $r->body());

            return $r->url() === 'https://vpn.test/api/partner/activations'
                && hash_equals($expected, $r->header('X-Mvpn-Signature')[0])
                && $r['reference'] === 'eduhub-order-'.$order->id
                && $r['eduhub_user_id'] === $user->id
                && $r['plan_code'] === 'm1'
                && $r['amount_cents'] === 1500000
                && $r['currency'] === 'tzs'
                && $r['email_verified'] === true;
        });
    }

    public function test_vpn_outage_keeps_payment_and_is_retried(): void
    {
        // First call: VPN down. Next call (the scheduled retry): VPN back.
        Http::fake(['vpn.test/api/partner/activations' => Http::sequence()
            ->push(['error' => 'down'], 500)
            ->push(['duplicate' => false, 'subscription' => ['expires_at' => '2026-11-10T10:00:00+00:00']], 201)]);
        ['order' => $order, 'payment' => $payment] = $this->vpnOrder();

        app(PaymentApprovalService::class)->approve($payment);

        $order->refresh();
        $this->assertSame('confirmed', $order->status, 'a VPN outage must not undo a verified payment');
        $this->assertNull($order->vpn_activated_at);
        $this->assertSame(1, $order->vpn_activation_attempts);
        $this->assertNotNull($order->vpn_activation_error);

        $this->artisan('mvpn:retry-activations')->assertSuccessful();

        $this->assertNotNull($order->fresh()->vpn_activated_at);
    }

    public function test_plan_without_mvpn_code_is_flagged_not_sent(): void
    {
        Http::fake();
        ['order' => $order, 'payment' => $payment] = $this->vpnOrder(code: null);

        app(PaymentApprovalService::class)->approve($payment);

        Http::assertNothingSent();
        $this->assertStringContainsString('MVPN plan code', $order->fresh()->vpn_activation_error);
    }

    public function test_non_vpn_products_never_call_the_vpn(): void
    {
        Http::fake();
        $this->artisan('mvpn:retry-activations')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_admin_vpn_page_is_super_admin_only(): void
    {
        Http::fake(['vpn.test/api/partner/stats' => Http::response([
            'customers' => 3,
            'subscriptions' => ['active' => 2, 'expiring_3d' => 1, 'expired' => 1, 'suspended' => 0],
            'payments' => ['pending' => 0, 'failed_30d' => 0, 'revenue_this_month' => ['tzs' => ['cents' => 3000000, 'count' => 2]]],
            'online_now' => ['devices' => 1, 'customers' => 1, 'list' => [
                ['customer' => 'buyer@example.com', 'platform' => 'android', 'protocol' => 'auto', 'connected_since' => now()->toIso8601String()],
            ]],
            'nodes' => [['name' => 'Kuala Lumpur 1', 'status' => 'online']],
            'recent_payments' => [],
        ])]);

        $super = User::factory()->create(['is_admin' => true, 'role' => 'super_admin', 'email_verified_at' => now()]);
        $plainAdmin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => ['payments.view'], 'email_verified_at' => now()]);
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($super)->get(route('admin.vpn.index'))->assertOk()
            ->assertSee('Wanaotumia VPN sasa')->assertSee('buyer@example.com')->assertSee('Kuala Lumpur 1');
        $this->actingAs($plainAdmin)->get(route('admin.vpn.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.vpn.index'))->assertForbidden();
    }

    public function test_customer_order_page_shows_vpn_download_once_active(): void
    {
        $this->fakeVpnOk();
        ['order' => $order, 'payment' => $payment, 'user' => $user] = $this->vpnOrder();
        app(PaymentApprovalService::class)->approve($payment);

        $this->actingAs($user)->get(route('user.orders.show', $order))->assertOk()
            ->assertSee('VPN active')
            ->assertSee('Sign in with MbunieHub')
            ->assertSee('/download/qr.svg', false);
    }
}
