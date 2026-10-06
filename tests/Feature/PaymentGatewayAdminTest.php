<?php

namespace Tests\Feature;

use App\Models\GatewayPayment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Admin\OnlinePaymentReceived;
use App\Notifications\User\PaymentReceipt;
use App\Services\Payments\GatewayPaymentService;
use App\Services\Payments\PaymentGatewaySettingsService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentGatewayAdminTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_SUPER_ADMIN,
            'permissions' => null,
        ]);
    }

    private function saveAzamPay(User $admin, array $overrides = [])
    {
        return $this->actingAs($admin)->put(route('admin.payment-methods.gateways.update', 'azampay'), array_merge([
            'enabled' => '1',
            'environment' => 'sandbox',
            'app_name' => 'mhub',
            'client_id' => 'client-123',
            'client_secret' => 'super-secret',
            'api_key' => 'api-key-456',
        ], $overrides));
    }

    public function test_page_separates_automatic_and_manual_payments(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.payment-methods.index'))
            ->assertOk()
            ->assertSeeInOrder(['Automatic payments', 'AzamPay', 'ClickPesa', 'PayPal', 'Manual payments', 'Add payment method'])
            ->assertSee(url('/api/payments/callback/azampay/'.config('payments.callback_token')));
    }

    public function test_admin_can_set_gateway_keys_from_the_panel(): void
    {
        $this->saveAzamPay($this->superAdmin())->assertSessionHasNoErrors();

        // Secrets are encrypted at rest.
        $stored = Setting::where('key', 'payment_gateway.azampay.client_secret')->value('value');
        $this->assertNotEmpty($stored);
        $this->assertStringNotContainsString('super-secret', $stored);

        // A fresh boot picks the saved values up.
        config(['payments.gateways.azampay.client_secret' => null, 'payments.gateways.azampay.enabled' => false]);
        app(PaymentGatewaySettingsService::class)->apply();

        $this->assertSame('super-secret', config('payments.gateways.azampay.client_secret'));
        $this->assertTrue(app(GatewayPaymentService::class)->gateway('azampay')->isEnabled());
    }

    public function test_blank_secret_keeps_the_saved_value(): void
    {
        $admin = $this->superAdmin();
        $this->saveAzamPay($admin);
        $this->saveAzamPay($admin, ['client_secret' => '', 'api_key' => '', 'environment' => 'live']);

        app(PaymentGatewaySettingsService::class)->apply();
        $this->assertSame('super-secret', config('payments.gateways.azampay.client_secret'));
        $this->assertSame('live', config('payments.gateways.azampay.environment'));
    }

    public function test_enabling_without_keys_warns_and_stays_off_at_checkout(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.payment-methods.gateways.update', 'clickpesa'), [
            'enabled' => '1',
        ])->assertSessionHas('error');

        $this->assertFalse(app(GatewayPaymentService::class)->gateway('clickpesa')->isEnabled());
    }

    public function test_test_connection_reports_success_and_failure(): void
    {
        $admin = $this->superAdmin();
        $this->saveAzamPay($admin);

        Http::fake(['authenticator-sandbox.azampay.co.tz/*' => Http::sequence()
            ->push(['data' => ['accessToken' => 'ok']])
            ->push(['message' => 'Invalid'], 401)]);

        $this->actingAs($admin)->post(route('admin.payment-methods.gateways.test', 'azampay'))
            ->assertSessionHas('success', 'AzamPay connected successfully.');

        $this->actingAs($admin)->post(route('admin.payment-methods.gateways.test', 'azampay'))
            ->assertSessionHas('error');
    }

    public function test_callback_urls_can_be_regenerated(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('admin.payment-methods.index'));
        $old = config('payments.callback_token');

        $this->actingAs($admin)->post(route('admin.payment-methods.callback-token'))->assertSessionHas('success');

        $this->assertNotSame($old, config('payments.callback_token'));
        $this->assertSame(48, strlen(config('payments.callback_token')));
    }

    public function test_restricted_admin_cannot_manage_gateways(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['payments.view']]);

        $this->saveAzamPay($admin)->assertForbidden();
    }

    public function test_online_payment_emails_receipt_to_customer_and_alerts_admins(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $this->saveAzamPay($admin);
        app(PaymentGatewaySettingsService::class)->callbackToken();
        $context = $this->createOrderContext();

        Http::fake([
            'authenticator-sandbox.azampay.co.tz/*' => Http::response(['data' => ['accessToken' => 't']]),
            'sandbox.azampay.co.tz/*' => Http::response(['success' => true, 'transactionId' => 'AZ1']),
        ]);
        $this->actingAs($context['user'])->post(route('user.payments.mobile.store', $context['order']), [
            'gateway' => 'azampay', 'network' => 'Mpesa', 'phone' => '0712345678',
        ]);

        $this->postJson(route('api.payments.callback', ['azampay', config('payments.callback_token')]), [
            'utilityref' => GatewayPayment::firstOrFail()->external_id,
            'transactionstatus' => 'success',
            'reference' => 'MPX1',
            'amount' => '45000',
        ])->assertOk();

        $this->assertSame('approved', Payment::firstOrFail()->status);
        Notification::assertSentTo($context['user'], PaymentReceipt::class);
        Notification::assertSentTo($admin, OnlinePaymentReceived::class);
        Notification::assertNotSentTo($context['user'], \App\Notifications\User\PaymentApproved::class);
    }

    public function test_receipt_and_admin_emails_render(): void
    {
        $context = $this->createOrderContext();
        $payment = Payment::create([
            'order_id' => $context['order']->id, 'user_id' => $context['user']->id, 'amount' => 45000,
            'payment_method' => 'paypal', 'transaction_reference' => 'CAP1', 'status' => 'approved',
        ]);
        $gatewayPayment = GatewayPayment::create([
            'order_id' => $context['order']->id, 'user_id' => $context['user']->id, 'payment_id' => $payment->id,
            'gateway' => 'paypal', 'phone' => '', 'amount' => 45000, 'currency' => 'TZS',
            'charged_amount' => 18, 'charged_currency' => 'USD', 'payer_email' => 'buyer@example.com',
            'external_id' => 'MHTEST', 'status' => 'success', 'completed_at' => now(),
        ]);

        $receipt = (string) (new PaymentReceipt($payment, $gatewayPayment))->toMail($context['user'])->render();
        $this->assertStringContainsString('RCPT-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), $receipt);
        $this->assertStringContainsString('TZS 45,000.00', $receipt);
        $this->assertStringContainsString('USD 18.00', $receipt);
        $this->assertStringContainsString('buyer@example.com', $receipt);
        $this->assertStringContainsString('signed/orders/'.$context['order']->id.'/receipt', $receipt);

        $admin = (string) (new OnlinePaymentReceived($payment, $gatewayPayment))->toMail($this->superAdmin())->render();
        $this->assertStringContainsString('New online payment received', $admin);
        $this->assertStringContainsString($context['order']->order_number, $admin);
    }
}
