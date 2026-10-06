<?php

namespace Tests\Feature;

use App\Models\GatewayPayment;
use App\Models\Payment;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileMoneyGatewayTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    private const TOKEN = 'test-callback-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.callback_token' => self::TOKEN,
            'payments.gateways.azampay.enabled' => true,
            'payments.gateways.azampay.app_name' => 'mhub',
            'payments.gateways.azampay.client_id' => 'client',
            'payments.gateways.azampay.client_secret' => 'secret',
            'payments.gateways.azampay.api_key' => 'api-key',
            'payments.gateways.clickpesa.enabled' => true,
            'payments.gateways.clickpesa.client_id' => 'cp-client',
            'payments.gateways.clickpesa.api_key' => 'cp-key',
        ]);
    }

    private function fakeAzamPay(): void
    {
        Http::fake([
            'authenticator-sandbox.azampay.co.tz/*' => Http::response(['data' => ['accessToken' => 'azam-token'], 'success' => true]),
            'sandbox.azampay.co.tz/azampay/mno/checkout' => Http::response(['success' => true, 'transactionId' => 'AZ123', 'message' => 'ok']),
        ]);
    }

    private function fakeClickPesa(string $status = 'PROCESSING'): void
    {
        Http::fake([
            'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer cp-token']),
            'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response(['id' => 'CP1', 'status' => 'PROCESSING']),
            'api.clickpesa.com/third-parties/payments/*' => Http::response([[
                'id' => 'CP1', 'status' => $status, 'paymentReference' => 'MPESA987', 'collectedAmount' => 45000,
            ]]),
        ]);
    }

    private function startPayment(array $context, string $gateway, array $extra = [])
    {
        return $this->actingAs($context['user'])->post(route('user.payments.mobile.store', $context['order']), array_merge([
            'gateway' => $gateway,
            'phone' => '0712 345 678',
        ], $extra));
    }

    public function test_phone_numbers_are_normalised(): void
    {
        $this->assertSame('255712345678', GatewayPaymentService::normalizePhone('0712 345 678'));
        $this->assertSame('255652345678', GatewayPaymentService::normalizePhone('+255 652 345 678'));
        $this->assertSame('255712345678', GatewayPaymentService::normalizePhone('712345678'));
        $this->assertNull(GatewayPaymentService::normalizePhone('0812345678'));
        $this->assertNull(GatewayPaymentService::normalizePhone('12345'));
    }

    public function test_azampay_push_then_success_callback_confirms_the_order(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();

        $this->startPayment($context, 'azampay', ['network' => 'Mpesa'])->assertRedirect();

        $gatewayPayment = GatewayPayment::firstOrFail();
        $this->assertSame('pending', $gatewayPayment->status);
        $this->assertSame('255712345678', $gatewayPayment->phone);
        $this->assertSame('AZ123', $gatewayPayment->provider_transaction_id);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/azampay/mno/checkout')
            && $request['provider'] === 'Mpesa'
            && $request['accountNumber'] === '255712345678'
            && $request['amount'] === '45000'
            && $request['externalId'] === $gatewayPayment->external_id
            && $request->hasHeader('X-API-Key', 'api-key'));

        $this->postJson(route('api.payments.callback', ['azampay', self::TOKEN]), [
            'utilityref' => $gatewayPayment->external_id,
            'transactionstatus' => 'success',
            'reference' => 'MP2610XYZ',
            'amount' => '45000',
            'msisdn' => '255712345678',
        ])->assertOk();

        $this->assertSame('success', $gatewayPayment->fresh()->status);
        $payment = Payment::firstOrFail();
        $this->assertSame('approved', $payment->status);
        $this->assertSame('azampay', $payment->payment_method);
        $this->assertSame('MP2610XYZ', $payment->transaction_reference);
        $this->assertSame('AzamPay', $payment->paymentMethodLabel());
        $this->assertSame('confirmed', $context['order']->fresh()->status);
        $this->assertNotNull($context['order']->fresh()->subscription);
    }

    public function test_duplicate_success_callbacks_create_one_payment(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();
        $this->startPayment($context, 'azampay', ['network' => 'Airtel']);
        $externalId = GatewayPayment::firstOrFail()->external_id;

        foreach (range(1, 2) as $i) {
            $this->postJson(route('api.payments.callback', ['azampay', self::TOKEN]), [
                'utilityref' => $externalId, 'transactionstatus' => 'success', 'reference' => 'R1', 'amount' => '45000',
            ])->assertOk();
        }

        $this->assertSame(1, Payment::count());
    }

    public function test_callback_with_wrong_token_is_rejected(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();
        $this->startPayment($context, 'azampay', ['network' => 'Mpesa']);

        $this->postJson(route('api.payments.callback', ['azampay', 'wrong']), [
            'utilityref' => GatewayPayment::firstOrFail()->external_id, 'transactionstatus' => 'success', 'amount' => '45000',
        ])->assertForbidden();

        $this->assertSame('pending', GatewayPayment::firstOrFail()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_failed_callback_keeps_the_order_pending(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();
        $this->startPayment($context, 'azampay', ['network' => 'Tigo']);

        $this->postJson(route('api.payments.callback', ['azampay', self::TOKEN]), [
            'utilityref' => GatewayPayment::firstOrFail()->external_id, 'transactionstatus' => 'failure', 'message' => 'Insufficient balance',
        ])->assertOk();

        $this->assertSame('failed', GatewayPayment::firstOrFail()->status);
        $this->assertSame('Insufficient balance', GatewayPayment::firstOrFail()->message);
        $this->assertSame('pending', $context['order']->fresh()->status);
    }

    public function test_underpayment_is_left_for_admin_review(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();
        $this->startPayment($context, 'azampay', ['network' => 'Mpesa']);

        $this->postJson(route('api.payments.callback', ['azampay', self::TOKEN]), [
            'utilityref' => GatewayPayment::firstOrFail()->external_id, 'transactionstatus' => 'success', 'amount' => '1000',
        ])->assertOk();

        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertStringContainsString('less than the order total', $payment->admin_note);
        $this->assertSame('pending', $context['order']->fresh()->status);
    }

    public function test_azampay_requires_a_network(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();

        $this->startPayment($context, 'azampay')->assertSessionHasErrors('network');
        $this->assertSame(0, GatewayPayment::count());
    }

    public function test_clickpesa_callback_is_verified_with_clickpesa(): void
    {
        $this->fakeClickPesa('SUCCESS');
        $context = $this->createOrderContext();

        $this->startPayment($context, 'clickpesa')->assertRedirect();
        $gatewayPayment = GatewayPayment::firstOrFail();

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'initiate-ussd-push-request')
            && $request['phoneNumber'] === '255712345678'
            && $request['orderReference'] === $gatewayPayment->external_id
            && $request->hasHeader('Authorization', 'Bearer cp-token'));

        // The callback body alone is not trusted: the status comes from ClickPesa's API.
        $this->postJson(route('api.payments.callback', ['clickpesa', self::TOKEN]), [
            'event' => 'PAYMENT RECEIVED',
            'data' => ['orderReference' => $gatewayPayment->external_id, 'status' => 'SUCCESS'],
        ])->assertOk();

        $this->assertSame('success', $gatewayPayment->fresh()->status);
        $this->assertSame('MPESA987', Payment::firstOrFail()->transaction_reference);
        $this->assertSame('confirmed', $context['order']->fresh()->status);
    }

    public function test_status_polling_confirms_a_clickpesa_payment(): void
    {
        $this->fakeClickPesa('SUCCESS');
        $context = $this->createOrderContext();
        $this->startPayment($context, 'clickpesa');
        $gatewayPayment = GatewayPayment::firstOrFail();

        $this->actingAs($context['user'])
            ->getJson(route('user.payments.mobile.status', [$context['order'], $gatewayPayment]))
            ->assertOk()
            ->assertJson(['status' => 'success', 'redirect' => route('user.orders.show', $context['order'])]);
    }

    public function test_unanswered_azampay_push_expires(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();
        $this->startPayment($context, 'azampay', ['network' => 'Mpesa']);

        $this->travel(11)->minutes();
        $this->artisan('payments:sync-gateway')->assertSuccessful();

        $this->assertSame('expired', GatewayPayment::firstOrFail()->status);
    }

    public function test_payment_page_offers_mobile_money_only_when_enabled(): void
    {
        $context = $this->createOrderContext();

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertOk()
            ->assertSee('Pay instantly with mobile money')
            ->assertSee('AzamPay')
            ->assertSee('ClickPesa');

        config(['payments.gateways.azampay.enabled' => false, 'payments.gateways.clickpesa.enabled' => false]);

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertOk()
            ->assertDontSee('Pay instantly with mobile money');
    }

    public function test_other_users_cannot_see_a_gateway_payment(): void
    {
        $this->fakeAzamPay();
        $context = $this->createOrderContext();
        $this->startPayment($context, 'azampay', ['network' => 'Mpesa']);
        $other = $this->createOrderContext();

        $this->actingAs($other['user'])
            ->getJson(route('user.payments.mobile.status', [$context['order'], GatewayPayment::firstOrFail()]))
            ->assertForbidden();
    }
}
