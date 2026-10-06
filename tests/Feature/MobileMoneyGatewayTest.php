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
        $this->travel(1)->minutes();

        $this->actingAs($context['user'])
            ->getJson(route('user.payments.mobile.status', [$context['order'], $gatewayPayment]))
            ->assertOk()
            ->assertJson(['status' => 'success', 'redirect' => route('user.orders.show', $context['order'])]);
    }

    public function test_clickpesa_status_is_checked_sparingly_to_save_the_daily_api_budget(): void
    {
        $this->fakeClickPesa('PROCESSING');
        $context = $this->createOrderContext();
        $this->startPayment($context, 'clickpesa');
        $gatewayPayment = GatewayPayment::firstOrFail();
        $statusUrl = route('user.payments.mobile.status', [$context['order'], $gatewayPayment]);
        $lookups = fn () => count(Http::recorded(fn ($request) => $request->method() === 'GET'));

        // Right after the push: no lookup yet, the customer is still typing the PIN.
        $this->actingAs($context['user'])->getJson($statusUrl)->assertJson(['status' => 'pending']);
        $this->assertSame(0, $lookups());

        // The waiting page polls every 4 seconds, but ClickPesa is asked at most every 45.
        $this->travel(1)->minutes();
        foreach (range(1, 5) as $i) {
            $this->actingAs($context['user'])->getJson($statusUrl);
        }
        $this->assertSame(1, $lookups());

        $this->travel(46)->seconds();
        $this->actingAs($context['user'])->getJson($statusUrl);
        $this->assertSame(2, $lookups());
    }

    public function test_clickpesa_failure_reason_is_shown_to_the_customer(): void
    {
        Http::fake([
            'api.clickpesa.com/third-parties/generate-token' => Http::response(['token' => 'Bearer cp-token']),
            'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response(['id' => 'CP1', 'status' => 'PROCESSING']),
            'api.clickpesa.com/third-parties/payments/*' => Http::response([['status' => 'FAILED', 'message' => 'Insufficient balance']]),
        ]);
        $context = $this->createOrderContext();
        $this->startPayment($context, 'clickpesa');
        $this->travel(1)->minutes();

        $this->actingAs($context['user'])
            ->getJson(route('user.payments.mobile.status', [$context['order'], GatewayPayment::firstOrFail()]))
            ->assertJson(['status' => 'failed', 'message' => 'Insufficient balance']);
    }

    public function test_clickpesa_rejection_with_a_list_of_errors_is_shown_not_crashed(): void
    {
        Http::fake([
            'api.clickpesa.com/third-parties/generate-token' => Http::response(['token' => 'Bearer cp-token']),
            'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response([
                'statusCode' => 400,
                'message' => ['phoneNumber must be a valid phone number', 'Payment method not available'],
                'error' => 'Bad Request',
            ], 400),
        ]);
        $context = $this->createOrderContext();

        $this->startPayment($context, 'clickpesa')
            ->assertRedirect()
            ->assertSessionHas('error', 'ClickPesa could not start the payment. (phoneNumber must be a valid phone number; Payment method not available)');

        $gatewayPayment = GatewayPayment::firstOrFail();
        $this->assertSame('failed', $gatewayPayment->status);
        $this->assertStringContainsString('Payment method not available', $gatewayPayment->message);
    }

    public function test_order_reference_fits_clickpesa_rules(): void
    {
        foreach ([1, 98765, 1234567890] as $id) {
            $order = new \App\Models\Order();
            $order->id = $id;
            $reference = GatewayPaymentService::externalId($order);

            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $reference);
            $this->assertLessThanOrEqual(20, strlen($reference));
        }
    }

    public function test_clickpesa_checksum_is_added_only_when_a_key_is_set(): void
    {
        $gateway = app(\App\Services\Payments\ClickPesaGateway::class);
        $payload = ['phoneNumber' => '255712345678', 'amount' => '1000', 'currency' => 'TZS', 'orderReference' => 'MH1TABC'];

        $this->assertArrayNotHasKey('checksum', $gateway->withChecksum($payload));

        config(['payments.gateways.clickpesa.checksum_key' => 'secret-key']);
        $signed = $gateway->withChecksum($payload);

        // Keys sorted alphabetically, compact JSON, HMAC-SHA256 hex.
        $expected = hash_hmac('sha256', '{"amount":"1000","currency":"TZS","orderReference":"MH1TABC","phoneNumber":"255712345678"}', 'secret-key');
        $this->assertSame($expected, $signed['checksum']);
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
