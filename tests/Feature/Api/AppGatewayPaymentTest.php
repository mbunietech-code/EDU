<?php

namespace Tests\Feature\Api;

use App\Models\GatewayPayment;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\CreatesOrderContext;
use Tests\TestCase;

class AppGatewayPaymentTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.gateways.azampay.enabled' => true,
            'payments.gateways.azampay.app_name' => 'mhub',
            'payments.gateways.azampay.client_id' => 'id',
            'payments.gateways.azampay.client_secret' => 'secret',
            'payments.gateways.clickpesa.client_id' => 'cp',
            'payments.gateways.clickpesa.api_key' => 'cp-key',
            'payments.gateways.clickpesa_card.enabled' => true,
            'payments.gateways.paypal.enabled' => true,
            'payments.gateways.paypal.client_id' => 'pp',
            'payments.gateways.paypal.client_secret' => 'pp-secret',
        ]);

        Cache::put('currency_rates', ['USD' => 0.0004, 'CNY' => 0.0026], now()->addHour());
    }

    private function fakeProviders(): void
    {
        Http::fake([
            'authenticator-sandbox.azampay.co.tz/*' => Http::response(['data' => ['accessToken' => 't']]),
            'sandbox.azampay.co.tz/*' => Http::response(['success' => true, 'transactionId' => 'AZ1']),
            'api.clickpesa.com/third-parties/generate-token' => Http::response(['token' => 'Bearer cp']),
            'api.clickpesa.com/third-parties/payments/initiate-card-payment' => Http::response(['cardPaymentLink' => 'https://pay.clickpesa.com/card/abc']),
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'pp']),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PP1/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP1', 'status' => 'COMPLETED', 'amount' => ['value' => '18.00']]]]]],
            ], 201),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PP1' => Http::response(['id' => 'PP1', 'status' => 'APPROVED']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PP1', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PP1']],
            ], 201),
        ]);
    }

    public function test_options_list_every_way_to_pay(): void
    {
        PaymentMethod::create(['code' => 'mpesa', 'name' => 'M-Pesa', 'enabled' => true, 'sort_order' => 1]);
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);

        $this->getJson("/api/orders/{$context['order']->id}/payment-options")
            ->assertOk()
            ->assertJsonPath('data.amount_label', 'TZS 45,000')
            ->assertJsonPath('data.mobile.0.key', 'azampay')
            ->assertJsonPath('data.mobile.0.networks.0', ['value' => 'Mpesa', 'label' => 'Vodacom M-Pesa'])
            ->assertJsonPath('data.card', ['amount' => 18, 'currency' => 'USD', 'label' => 'Card (Visa / Mastercard)'])
            ->assertJsonPath('data.paypal.currency', 'USD')
            ->assertJsonPath('data.manual', true)
            ->assertJsonPath('data.active', null);
    }

    public function test_mobile_money_push_from_the_app(): void
    {
        $this->fakeProviders();
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);

        $this->postJson("/api/orders/{$context['order']->id}/payments/mobile", [
            'gateway' => 'azampay', 'network' => 'Mpesa', 'phone' => '0712 345 678',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.phone', '255712345678')
            ->assertJsonPath('data.uses_redirect', false);

        // A second push while the first is waiting is refused but points to it.
        $this->postJson("/api/orders/{$context['order']->id}/payments/mobile", [
            'gateway' => 'azampay', 'network' => 'Mpesa', 'phone' => '0712 345 678',
        ])->assertStatus(409)->assertJsonPath('data.id', GatewayPayment::firstOrFail()->id);
    }

    public function test_bad_phone_returns_a_field_error(): void
    {
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);

        $this->postJson("/api/orders/{$context['order']->id}/payments/mobile", [
            'gateway' => 'azampay', 'network' => 'Mpesa', 'phone' => '123',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_card_payment_returns_the_card_page_link(): void
    {
        $this->fakeProviders();
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);

        $this->postJson("/api/orders/{$context['order']->id}/payments/card", [
            'card_name' => 'Asha Juma', 'card_phone' => '0712345678',
        ])->assertCreated()
            ->assertJsonPath('data.redirect_url', 'https://pay.clickpesa.com/card/abc')
            ->assertJsonPath('data.charged_label', 'USD 18.00')
            ->assertJsonPath('data.uses_redirect', true);
    }

    public function test_paypal_from_the_app_returns_to_a_page_without_login(): void
    {
        $this->fakeProviders();
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);

        $this->postJson("/api/orders/{$context['order']->id}/payments/paypal")
            ->assertCreated()
            ->assertJsonPath('data.redirect_url', 'https://www.sandbox.paypal.com/checkoutnow?token=PP1');

        $reference = GatewayPayment::firstOrFail()->external_id;
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
            && $request['application_context']['return_url'] === route('payments.gateway.return', $reference));

        // The phone browser comes back with no session; the payment is captured.
        auth()->guard('web')->logout();
        $this->get(route('payments.gateway.return', $reference).'?token=PP1&PayerID=X')
            ->assertOk()
            ->assertSee('Payment received');

        $this->assertSame('success', GatewayPayment::firstOrFail()->status);
        $this->assertSame('confirmed', $context['order']->fresh()->status);
    }

    public function test_cancel_page_marks_the_payment_failed(): void
    {
        $this->fakeProviders();
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);
        $this->postJson("/api/orders/{$context['order']->id}/payments/paypal");

        $this->get(route('payments.gateway.cancel', GatewayPayment::firstOrFail()->external_id))
            ->assertOk()
            ->assertSee('Payment not completed');

        $this->assertSame('failed', GatewayPayment::firstOrFail()->status);
    }

    public function test_status_is_private_to_the_payer(): void
    {
        $this->fakeProviders();
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);
        $this->postJson("/api/orders/{$context['order']->id}/payments/mobile", [
            'gateway' => 'azampay', 'network' => 'Mpesa', 'phone' => '0712345678',
        ]);
        $id = GatewayPayment::firstOrFail()->id;

        $this->getJson("/api/gateway-payments/{$id}")->assertOk()->assertJsonPath('data.status', 'pending');

        Sanctum::actingAs($this->createOrderContext()['user']);
        $this->getJson("/api/gateway-payments/{$id}")->assertNotFound();
    }

    public function test_unknown_return_reference_is_not_found(): void
    {
        $this->get('/pay/return/NOPE12345')->assertNotFound();
        $this->get('/pay/return/bad-chars!')->assertNotFound();
    }
}
