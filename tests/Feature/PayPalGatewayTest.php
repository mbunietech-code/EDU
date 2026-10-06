<?php

namespace Tests\Feature;

use App\Models\GatewayPayment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalGatewayTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    private const BASE = 'api-m.sandbox.paypal.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.gateways.paypal.enabled' => true,
            'payments.gateways.paypal.client_id' => 'pp-client',
            'payments.gateways.paypal.client_secret' => 'pp-secret',
        ]);

        // 1 TZS = 0.0004 USD, so the TZS 45,000 test order costs USD 18.00.
        Cache::put('currency_rates', ['USD' => 0.0004, 'CNY' => 0.0026], now()->addHour());
    }

    private function fakePayPal(string $orderStatus = 'APPROVED', string $captureValue = '18.00'): void
    {
        $completed = [
            'id' => 'PPORDER1',
            'status' => 'COMPLETED',
            'payer' => ['email_address' => 'buyer@example.com'],
            'purchase_units' => [[
                'payments' => ['captures' => [['id' => 'CAPTURE9', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => $captureValue]]]],
            ]],
        ];

        Http::fake([
            self::BASE.'/v1/oauth2/token' => Http::response(['access_token' => 'pp-token', 'expires_in' => 32000]),
            self::BASE.'/v2/checkout/orders/PPORDER1/capture' => Http::response($completed, 201),
            self::BASE.'/v2/checkout/orders/PPORDER1' => Http::response(['id' => 'PPORDER1', 'status' => $orderStatus]),
            self::BASE.'/v2/checkout/orders' => Http::response([
                'id' => 'PPORDER1',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PPORDER1'],
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PPORDER1'],
                ],
            ], 201),
        ]);
    }

    public function test_paypal_checkout_redirects_and_captures_on_return(): void
    {
        $this->fakePayPal();
        $context = $this->createOrderContext();

        $this->actingAs($context['user'])
            ->post(route('user.payments.paypal.start', $context['order']))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PPORDER1');

        $gatewayPayment = GatewayPayment::firstOrFail();
        $this->assertSame('18.00', $gatewayPayment->charged_amount);
        $this->assertSame('USD', $gatewayPayment->charged_currency);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://'.self::BASE.'/v2/checkout/orders'
            && $request['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '18.00']
            && $request['purchase_units'][0]['custom_id'] === $gatewayPayment->external_id
            && $request->hasHeader('Authorization', 'Bearer pp-token'));

        $this->actingAs($context['user'])
            ->get(route('user.payments.paypal.return', [$context['order'], 'token' => 'PPORDER1', 'PayerID' => 'X1']))
            ->assertRedirect(route('user.orders.show', $context['order']));

        $gatewayPayment->refresh();
        $this->assertSame('success', $gatewayPayment->status);
        $this->assertSame('buyer@example.com', $gatewayPayment->payer_email);

        $payment = Payment::firstOrFail();
        $this->assertSame('approved', $payment->status);
        $this->assertSame('paypal', $payment->payment_method);
        $this->assertSame('CAPTURE9', $payment->transaction_reference);
        $this->assertSame('45000.00', $payment->amount);
        $this->assertSame('PayPal', $payment->paymentMethodLabel());
        $this->assertSame('confirmed', $context['order']->fresh()->status);
    }

    public function test_cancelled_paypal_payment_keeps_order_pending(): void
    {
        $this->fakePayPal();
        $context = $this->createOrderContext();
        $this->actingAs($context['user'])->post(route('user.payments.paypal.start', $context['order']));

        $this->actingAs($context['user'])
            ->get(route('user.payments.paypal.cancel', [$context['order'], 'token' => 'PPORDER1']))
            ->assertRedirect(route('user.payments.create', [$context['order'], 'tab' => 'paypal']));

        $this->assertSame('failed', GatewayPayment::firstOrFail()->status);
        $this->assertSame(0, Payment::count());
        $this->assertSame('pending', $context['order']->fresh()->status);
    }

    public function test_cron_captures_an_approved_order_when_customer_never_returned(): void
    {
        $this->fakePayPal();
        $context = $this->createOrderContext();
        $this->actingAs($context['user'])->post(route('user.payments.paypal.start', $context['order']));

        $this->travel(1)->minutes();
        $this->artisan('payments:sync-gateway')->assertSuccessful();

        $this->assertSame('success', GatewayPayment::firstOrFail()->status);
        $this->assertSame('confirmed', $context['order']->fresh()->status);
    }

    public function test_unapproved_paypal_order_waits_longer_than_mobile_money(): void
    {
        $this->fakePayPal('PAYER_ACTION_REQUIRED');
        $context = $this->createOrderContext();
        $this->actingAs($context['user'])->post(route('user.payments.paypal.start', $context['order']));

        $this->travel(30)->minutes();
        $this->artisan('payments:sync-gateway');
        $this->assertSame('pending', GatewayPayment::firstOrFail()->status);

        $this->travel(3)->hours();
        $this->artisan('payments:sync-gateway');
        $this->assertSame('expired', GatewayPayment::firstOrFail()->status);
    }

    public function test_partial_capture_is_left_for_admin_review(): void
    {
        $this->fakePayPal('APPROVED', '9.00');
        $context = $this->createOrderContext();
        $this->actingAs($context['user'])->post(route('user.payments.paypal.start', $context['order']));

        $this->actingAs($context['user'])
            ->get(route('user.payments.paypal.return', [$context['order'], 'token' => 'PPORDER1']));

        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('22500.00', $payment->amount);
        $this->assertSame('pending', $context['order']->fresh()->status);
    }

    public function test_payment_page_shows_paypal_with_usd_amount(): void
    {
        $context = $this->createOrderContext();

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertOk()
            ->assertSee('Pay with PayPal or card')
            ->assertSee('USD 18.00')
            ->assertDontSee('Pay instantly with mobile money');
    }

    public function test_return_with_someone_elses_token_is_not_found(): void
    {
        $this->fakePayPal();
        $context = $this->createOrderContext();
        $this->actingAs($context['user'])->post(route('user.payments.paypal.start', $context['order']));

        $this->actingAs($context['user'])
            ->get(route('user.payments.paypal.return', [$context['order'], 'token' => 'OTHER']))
            ->assertNotFound();
    }
}
