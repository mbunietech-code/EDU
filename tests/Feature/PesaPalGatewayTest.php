<?php

namespace Tests\Feature;

use App\Models\GatewayPayment;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentChannelService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PesaPalGatewayTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    private const TOKEN = 'test-callback-secret';

    private const BASE = 'cybqa.pesapal.com/pesapalv3';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.callback_token' => self::TOKEN,
            'payments.gateways.pesapal.enabled' => true,
            'payments.gateways.pesapal.environment' => 'sandbox',
            'payments.gateways.pesapal.consumer_key' => 'pp-consumer-key',
            'payments.gateways.pesapal.consumer_secret' => 'pp-consumer-secret',
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function switchOn(string ...$codes): void
    {
        // Rows are created on first use (on for gateways already live): start
        // from all off so only the listed channels are on.
        app(PaymentChannelService::class)->all();
        PaymentChannel::query()->update(['enabled' => false]);
        PaymentChannel::whereIn('code', $codes)->update(['enabled' => true]);
    }

    /**
     * @param  array<string, mixed>|null  $status  GetTransactionStatus body; merchant_reference defaults to the payment's
     */
    private function fakePesaPal(?array $status = null): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response(['token' => 'pp-token', 'expiryDate' => now()->addMinutes(5)->toIso8601String(), 'error' => null, 'status' => '200']),
            self::BASE.'/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'IPN-1', 'url' => 'x', 'error' => null, 'status' => '200']),
            self::BASE.'/api/Transactions/SubmitOrderRequest' => fn (HttpRequest $request) => Http::response([
                'order_tracking_id' => 'TRK-1',
                'merchant_reference' => $request['id'],
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index?OrderTrackingId=TRK-1',
                'error' => null,
                'status' => '200',
            ]),
            self::BASE.'/api/Transactions/GetTransactionStatus*' => fn () => Http::response(array_merge([
                'payment_method' => 'MpesaTZ',
                'amount' => 45000,
                'currency' => 'TZS',
                'confirmation_code' => 'PPCONF123',
                'payment_status_description' => 'Completed',
                'status_code' => 1,
                'merchant_reference' => GatewayPayment::first()?->external_id,
                'error' => ['error_type' => null, 'code' => null, 'message' => null],
                'status' => '200',
            ], $status ?? [])),
        ]);
    }

    private function payWithChannel(array $context, string $channel, array $extra = [])
    {
        return $this->actingAs($context['user'])->post(route('user.payments.channel.store', $context['order']), array_merge([
            'channel' => $channel,
            'phone' => '0754 123 456',
        ], $extra));
    }

    private function ipn(string $reference, string $token = self::TOKEN)
    {
        return $this->postJson(route('api.payments.callback', ['pesapal', $token]), [
            'OrderTrackingId' => 'TRK-1',
            'OrderMerchantReference' => $reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]);
    }

    public function test_admin_saves_pesapal_keys_encrypted_and_never_shows_them(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('admin.payment-methods.gateways.update', 'pesapal'), [
            'enabled' => '1',
            'environment' => 'live',
            'consumer_key' => 'live-key-123',
            'consumer_secret' => 'live-secret-456',
        ])->assertSessionHas('success');

        $stored = Setting::where('key', 'payment_gateway.pesapal.consumer_secret')->value('value');
        $this->assertNotEmpty($stored);
        $this->assertStringNotContainsString('live-secret-456', $stored);
        $this->assertStringNotContainsString('live-key-123', (string) Setting::where('key', 'payment_gateway.pesapal.consumer_key')->value('value'));
        $this->assertSame('live-secret-456', config('payments.gateways.pesapal.consumer_secret'));
        $this->assertSame('live', config('payments.gateways.pesapal.environment'));

        $this->actingAs($admin)->get(route('admin.payment-methods.index', ['tab' => 'automatic']))
            ->assertOk()
            ->assertSee('PesaPal')
            ->assertDontSee('live-secret-456')
            ->assertDontSee('live-key-123');

        // The admin app sees only that a secret is saved.
        Sanctum::actingAs($admin);
        $row = collect($this->getJson('/api/admin/payment-gateways')->assertOk()->json('data'))->firstWhere('key', 'pesapal');
        $this->assertNull(collect($row['fields'])->firstWhere('key', 'consumer_secret')['value']);
        $this->assertTrue(collect($row['fields'])->firstWhere('key', 'consumer_secret')['is_set']);
    }

    public function test_new_channels_start_off_and_stay_hidden_until_switched_on(): void
    {
        config(['payments.gateways.pesapal.enabled' => false]);
        $channels = app(PaymentChannelService::class)->all();

        $this->assertTrue($channels->where('gateway', 'pesapal')->isNotEmpty());
        $this->assertTrue($channels->where('gateway', 'pesapal')->every(fn ($c) => ! $c->enabled));
        foreach (['pesapal_mpesa', 'pesapal_airtel', 'pesapal_tigo', 'pesapal_card', 'pesapal_bank', 'clickpesa_halopesa', 'clickpesa_card'] as $code) {
            $this->assertFalse((bool) $channels->firstWhere('code', $code)?->enabled, "$code should start off");
        }

        // Gateway live, but no channel switched on: nothing offered.
        config(['payments.gateways.pesapal.enabled' => true]);
        $context = $this->createOrderContext();

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertOk()
            ->assertDontSee('data-channel-panel="pesapal_card"', false);

        $this->payWithChannel($context, 'pesapal_card')->assertSessionHas('error');
        $this->assertSame(0, GatewayPayment::count());

        Sanctum::actingAs($context['user']);
        $this->getJson("/api/orders/{$context['order']->id}/payment-options")
            ->assertOk()
            ->assertJsonMissing(['code' => 'pesapal_card']);

        // Switched on by the admin: offered.
        $this->actingAs($this->superAdmin())->put(route('admin.payment-methods.channels.update'), [
            'channels' => [['code' => 'pesapal_card', 'enabled' => '1']],
        ])->assertRedirect();

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertSee('data-channel-panel="pesapal_card"', false);
    }

    public function test_channel_is_hidden_while_its_gateway_is_off(): void
    {
        $this->switchOn('pesapal_mpesa');
        config(['payments.gateways.pesapal.consumer_secret' => null]);
        $context = $this->createOrderContext();

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertDontSee('data-channel-panel="pesapal_mpesa"', false);
    }

    public function test_checkout_and_app_follow_the_admin_priority(): void
    {
        config([
            'payments.gateways.clickpesa.enabled' => true,
            'payments.gateways.clickpesa.client_id' => 'cp',
            'payments.gateways.clickpesa.api_key' => 'cp-key',
        ]);
        $this->switchOn('pesapal_card', 'pesapal_airtel', 'clickpesa_mpesa');

        $this->actingAs($this->superAdmin())->put(route('admin.payment-methods.channels.update'), [
            'channels' => [
                ['code' => 'pesapal_card', 'enabled' => '1'],
                ['code' => 'clickpesa_airtel', 'enabled' => '0'],
                ['code' => 'pesapal_airtel', 'enabled' => '1'],
                ['code' => 'clickpesa_mpesa', 'enabled' => '1'],
            ],
        ])->assertRedirect(route('admin.payment-methods.index', ['tab' => 'channels']));

        $this->assertSame(1, PaymentChannel::where('code', 'pesapal_card')->value('sort_order'));
        $this->assertFalse((bool) PaymentChannel::where('code', 'clickpesa_airtel')->value('enabled'));

        $context = $this->createOrderContext();

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertOk()
            ->assertSeeInOrder(['data-channel="pesapal_card"', 'data-channel="pesapal_airtel"', 'data-channel="clickpesa_mpesa"'], false)
            ->assertDontSee('data-channel="clickpesa_airtel"', false);

        Sanctum::actingAs($context['user']);
        $codes = collect($this->getJson("/api/orders/{$context['order']->id}/payment-options")->assertOk()->json('data.channels'))->pluck('code')->all();
        $this->assertSame(['pesapal_card', 'pesapal_airtel', 'clickpesa_mpesa'], array_values(array_diff($codes, ['manual'])));

        // Move M-Pesa to the top: the order flips.
        $this->actingAs($this->superAdmin())->put(route('admin.payment-methods.channels.update'), [
            'channels' => [
                ['code' => 'clickpesa_mpesa', 'enabled' => '1'],
                ['code' => 'pesapal_card', 'enabled' => '1'],
                ['code' => 'pesapal_airtel', 'enabled' => '1'],
            ],
        ]);

        $this->actingAs($context['user'])->get(route('user.payments.create', $context['order']))
            ->assertSeeInOrder(['data-channel="clickpesa_mpesa"', 'data-channel="pesapal_card"', 'data-channel="pesapal_airtel"'], false);
    }

    public function test_only_permitted_admins_change_channels(): void
    {
        $staff = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => ['payments.view']]);

        $this->actingAs($staff)->put(route('admin.payment-methods.channels.update'), [
            'channels' => [['code' => 'pesapal_card', 'enabled' => '1']],
        ])->assertForbidden();

        $this->actingAs($this->superAdmin())->put(route('admin.payment-methods.channels.update'), [
            'channels' => [['code' => 'made_up', 'enabled' => '1']],
        ])->assertSessionHasErrors('channels.0.code');
    }

    public function test_pesapal_form_opens_inside_our_checkout_and_ipn_confirms_after_status_check(): void
    {
        $this->fakePesaPal();
        $this->switchOn('pesapal_mpesa');
        $context = $this->createOrderContext();

        $response = $this->payWithChannel($context, 'pesapal_mpesa');

        $gatewayPayment = GatewayPayment::firstOrFail();
        // Stays on our site: the waiting page, with PesaPal's form framed inside.
        $response->assertRedirect(route('user.payments.mobile.show', [$context['order'], $gatewayPayment]));
        $this->assertSame('pesapal', $gatewayPayment->gateway);
        $this->assertSame('TRK-1', $gatewayPayment->provider_transaction_id);
        $this->assertSame('255754123456', $gatewayPayment->phone);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'SubmitOrderRequest')
            && $request['id'] === $gatewayPayment->external_id
            && $request['currency'] === 'TZS'
            && (float) $request['amount'] === 45000.0
            && $request['notification_id'] === 'IPN-1'
            && $request['redirect_mode'] === 'PARENT_WINDOW'
            && $request['billing_address']['phone_number'] === '0754123456'
            && $request->hasHeader('Authorization', 'Bearer pp-token'));
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'RegisterIPN')
            && $request['url'] === route('api.payments.callback', ['pesapal', self::TOKEN])
            && $request['ipn_notification_type'] === 'POST');

        $this->actingAs($context['user'])->get(route('user.payments.mobile.show', [$context['order'], $gatewayPayment]))
            ->assertOk()
            ->assertSee('<iframe src="https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index?OrderTrackingId=TRK-1"', false);

        $this->assertSame(0, Payment::count());

        $this->ipn($gatewayPayment->external_id)
            ->assertOk()
            ->assertExactJson([
                'orderNotificationType' => 'IPNCHANGE',
                'orderTrackingId' => 'TRK-1',
                'orderMerchantReference' => $gatewayPayment->external_id,
                'status' => 200,
            ]);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'GetTransactionStatus')
            && $request['orderTrackingId'] === 'TRK-1');

        $this->assertSame('success', $gatewayPayment->fresh()->status);
        $payment = Payment::firstOrFail();
        $this->assertSame('approved', $payment->status);
        $this->assertSame('pesapal', $payment->payment_method);
        $this->assertSame('PPCONF123', $payment->transaction_reference);
        $this->assertSame('PesaPal', $payment->paymentMethodLabel());
        $this->assertSame('confirmed', $context['order']->fresh()->status);
        $this->assertNotNull($context['order']->fresh()->subscription);
    }

    public function test_return_from_the_pesapal_form_confirms_right_away(): void
    {
        $this->fakePesaPal();
        $this->switchOn('pesapal_card');
        $context = $this->createOrderContext();
        $this->payWithChannel($context, 'pesapal_card');
        $gatewayPayment = GatewayPayment::firstOrFail();

        // A made-up tracking id is ignored: no forced check, still pending.
        $this->actingAs($context['user'])
            ->get(route('user.payments.mobile.show', [$context['order'], $gatewayPayment]).'?OrderTrackingId=FAKE')
            ->assertOk();
        $this->assertSame('pending', $gatewayPayment->fresh()->status);

        $this->actingAs($context['user'])
            ->get(route('user.payments.mobile.show', [$context['order'], $gatewayPayment]).'?OrderTrackingId=TRK-1&OrderMerchantReference='.$gatewayPayment->external_id)
            ->assertRedirect(route('user.orders.show', $context['order']));

        $this->assertSame('success', $gatewayPayment->fresh()->status);
        $this->assertSame('confirmed', $context['order']->fresh()->status);
    }

    public function test_ipn_does_not_mark_paid_while_pesapal_says_unpaid(): void
    {
        $this->fakePesaPal(['status_code' => 0, 'payment_status_description' => 'INVALID', 'confirmation_code' => '', 'status' => '500',
            'error' => ['error_type' => 'api_error', 'code' => 'payment_details_not_found', 'message' => 'Pending Payment']]);
        $this->switchOn('pesapal_card');
        $context = $this->createOrderContext();
        $this->payWithChannel($context, 'pesapal_card', ['phone' => '']);

        $gatewayPayment = GatewayPayment::firstOrFail();
        $this->ipn($gatewayPayment->external_id)->assertOk();

        $this->assertSame('pending', $gatewayPayment->fresh()->status);
        $this->assertSame(0, Payment::count());
        $this->assertSame('pending', $context['order']->fresh()->status);
    }

    public function test_failed_status_marks_the_payment_failed(): void
    {
        $this->fakePesaPal(['status_code' => 2, 'payment_status_description' => 'Failed', 'description' => 'Insufficient balance']);
        $this->switchOn('pesapal_airtel');
        $context = $this->createOrderContext();
        $this->payWithChannel($context, 'pesapal_airtel');

        $this->ipn(GatewayPayment::firstOrFail()->external_id)->assertOk();

        $this->assertSame('failed', GatewayPayment::firstOrFail()->status);
        $this->assertSame('Insufficient balance', GatewayPayment::firstOrFail()->message);
        $this->assertSame(0, Payment::count());
    }

    public function test_forged_callbacks_are_rejected(): void
    {
        $this->fakePesaPal(['merchant_reference' => 'SOMEOTHERORDER1']);
        $this->switchOn('pesapal_mpesa');
        $context = $this->createOrderContext();
        $this->payWithChannel($context, 'pesapal_mpesa');
        $gatewayPayment = GatewayPayment::firstOrFail();

        // Wrong secret in the URL.
        $this->ipn($gatewayPayment->external_id, 'wrong-token')->assertForbidden();

        // Right URL, but PesaPal's answer is about another order: not paid.
        $this->ipn($gatewayPayment->external_id)->assertOk();
        $this->assertSame('pending', $gatewayPayment->fresh()->status);

        // Unknown or malformed reference: nothing found, PesaPal told so.
        $this->ipn('NOTAREALORDER99')->assertOk()->assertJsonPath('status', 500);
        $this->ipn("x' OR 1=1 --")->assertOk()->assertJsonPath('status', 500);

        $this->assertSame(0, Payment::count());
        $this->assertSame('pending', $context['order']->fresh()->status);
    }

    public function test_underpaid_or_foreign_currency_is_left_for_admin_review(): void
    {
        $this->fakePesaPal(['amount' => 10, 'currency' => 'USD']);
        $this->switchOn('pesapal_card');
        $context = $this->createOrderContext();
        $this->payWithChannel($context, 'pesapal_card');

        $this->ipn(GatewayPayment::firstOrFail()->external_id)->assertOk();

        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertStringContainsString('less than the order total', (string) $payment->admin_note);
        $this->assertSame('pending', $context['order']->fresh()->status);
    }

    public function test_mobile_channel_needs_a_valid_tanzanian_number(): void
    {
        $this->fakePesaPal();
        $this->switchOn('pesapal_mpesa');
        $context = $this->createOrderContext();

        $this->payWithChannel($context, 'pesapal_mpesa', ['phone' => '0812 000'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, GatewayPayment::count());
    }

    public function test_app_pays_through_a_channel_and_returns_without_web_login(): void
    {
        $this->fakePesaPal();
        $this->switchOn('pesapal_mpesa');
        $context = $this->createOrderContext();
        Sanctum::actingAs($context['user']);

        $this->getJson("/api/orders/{$context['order']->id}/payment-options")
            ->assertOk()
            ->assertJsonFragment(['code' => 'pesapal_mpesa', 'flow' => 'hosted', 'gateway_label' => 'PesaPal', 'needs_phone' => true, 'currency' => 'TZS']);

        $this->postJson("/api/orders/{$context['order']->id}/payments/channel", ['channel' => 'pesapal_mpesa', 'phone' => '0754123456'])
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'pesapal')
            ->assertJsonPath('data.uses_redirect', true)
            ->assertJsonPath('data.redirect_url', 'https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index?OrderTrackingId=TRK-1');

        $gatewayPayment = GatewayPayment::firstOrFail();
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'SubmitOrderRequest')
            && $request['callback_url'] === route('payments.gateway.return', $gatewayPayment->external_id));

        $this->get(route('payments.gateway.return', $gatewayPayment->external_id))->assertOk();
        $this->assertSame('success', $gatewayPayment->fresh()->status);

        $this->postJson("/api/orders/{$context['order']->id}/payments/channel", ['channel' => 'pesapal_bank'])
            ->assertStatus(422);
    }

    public function test_token_failure_shows_a_safe_message_without_the_secret(): void
    {
        Http::fake([
            self::BASE.'/api/Auth/RequestToken' => Http::response(['token' => null, 'error' => ['code' => 'invalid_consumer_key_or_secret_provided', 'message' => 'Invalid consumer key or secret'], 'status' => '500']),
        ]);

        $this->actingAs($this->superAdmin())->post(route('admin.payment-methods.gateways.test', 'pesapal'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Invalid consumer key or secret')
                && ! str_contains($message, 'pp-consumer-secret'));
    }
}
