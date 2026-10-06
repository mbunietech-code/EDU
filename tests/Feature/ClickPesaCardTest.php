<?php

namespace Tests\Feature;

use App\Models\GatewayPayment;
use App\Models\Payment;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClickPesaCardTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    private const TOKEN = 'card-callback-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.callback_token' => self::TOKEN,
            'payments.gateways.clickpesa.client_id' => 'cp-client',
            'payments.gateways.clickpesa.api_key' => 'cp-key',
            'payments.gateways.clickpesa_card.enabled' => true,
        ]);

        // 1 TZS = 0.0004 USD: the TZS 45,000 test order costs USD 18.00.
        Cache::put('currency_rates', ['USD' => 0.0004, 'CNY' => 0.0026], now()->addHour());
    }

    private function fakeClickPesa(string $status = 'PROCESSING'): void
    {
        Http::fake([
            'api.clickpesa.com/third-parties/generate-token' => Http::response(['token' => 'Bearer cp-token']),
            'api.clickpesa.com/third-parties/payments/initiate-card-payment' => Http::response([
                'cardPaymentLink' => 'https://pay.clickpesa.com/card/session-123',
                'clientId' => 'cp-client',
            ]),
            'api.clickpesa.com/third-parties/payments/*' => Http::response([[
                'status' => $status, 'paymentReference' => 'CARD777', 'collectedAmount' => 17, 'message' => 'success',
            ]]),
        ]);
    }

    private function payByCard(array $context)
    {
        return $this->actingAs($context['user'])->post(route('user.payments.card.store', $context['order']), [
            'card_name' => 'Asha Juma',
            'card_phone' => '0712 345 678',
        ]);
    }

    public function test_card_payment_opens_clickpesa_card_page_in_usd(): void
    {
        $this->fakeClickPesa();
        $context = $this->createOrderContext();

        $this->payByCard($context)->assertRedirect();
        $gatewayPayment = GatewayPayment::firstOrFail();

        $this->assertSame('clickpesa_card', $gatewayPayment->gateway);
        $this->assertSame('18.00', $gatewayPayment->charged_amount);
        $this->assertSame('USD', $gatewayPayment->charged_currency);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'initiate-card-payment')
            && $request['amount'] === '18.00'
            && $request['currency'] === 'USD'
            && $request['orderReference'] === $gatewayPayment->external_id
            && $request['customer'] === ['fullName' => 'Asha Juma', 'email' => $context['user']->email, 'phoneNumber' => '255712345678']);

        $this->actingAs($context['user'])
            ->get(route('user.payments.mobile.show', [$context['order'], $gatewayPayment]))
            ->assertOk()
            ->assertSee('Open secure card page')
            ->assertSee('https://pay.clickpesa.com/card/session-123', false);
    }

    public function test_clickpesa_webhook_confirms_a_card_payment(): void
    {
        $this->fakeClickPesa('SUCCESS');
        $context = $this->createOrderContext();
        $this->payByCard($context);

        // ClickPesa sends card results to the same webhook URL as mobile money.
        $this->postJson(route('api.payments.callback', ['clickpesa', self::TOKEN]), [
            'event' => 'PAYMENT RECEIVED',
            'data' => ['orderReference' => GatewayPayment::firstOrFail()->external_id, 'status' => 'SUCCESS'],
        ])->assertOk();

        $payment = Payment::firstOrFail();
        $this->assertSame('approved', $payment->status);
        $this->assertSame('clickpesa_card', $payment->payment_method);
        $this->assertSame('45000.00', $payment->amount);
        $this->assertSame('Card (Visa / Mastercard)', $payment->paymentMethodLabel());
        $this->assertSame('confirmed', $context['order']->fresh()->status);
    }

    public function test_card_needs_clickpesa_keys_and_its_own_switch(): void
    {
        $card = app(GatewayPaymentService::class)->gateway('clickpesa_card');
        $this->assertTrue($card->isEnabled());

        config(['payments.gateways.clickpesa_card.enabled' => false]);
        $this->assertFalse($card->isEnabled());

        config(['payments.gateways.clickpesa_card.enabled' => true, 'payments.gateways.clickpesa.api_key' => null]);
        $this->assertFalse($card->isEnabled());
    }

    public function test_card_is_offered_on_the_payment_page_but_not_in_the_mobile_money_list(): void
    {
        $context = $this->createOrderContext();

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertOk()
            ->assertSee('Pay by card (Visa / Mastercard)')
            ->assertSee('USD 18.00')
            ->assertDontSee('Pay instantly with mobile money');
    }

    public function test_invalid_phone_is_rejected_before_calling_clickpesa(): void
    {
        $this->fakeClickPesa();
        $context = $this->createOrderContext();

        $this->actingAs($context['user'])->post(route('user.payments.card.store', $context['order']), [
            'card_name' => 'Asha Juma',
            'card_phone' => '123',
        ])->assertSessionHasErrors('card_phone');

        Http::assertNothingSent();
    }
}
