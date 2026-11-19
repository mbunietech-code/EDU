<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentPageTabsTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::put('currency_rates', ['USD' => 0.0004, 'CNY' => 0.0026], now()->addHour());
    }

    private function enableEverything(): void
    {
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

        PaymentMethod::create(['code' => 'mpesa', 'name' => 'M-Pesa', 'qr_image' => 'payment-methods/mpesa.png', 'enabled' => true, 'sort_order' => 1]);
    }

    private function page(array $context, array $query = [], array $errors = [])
    {
        $request = $this->actingAs($context['user']);

        if ($errors) {
            $request = $request->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag($errors))]);
        }

        return $request->get(route('user.payments.create', array_merge([$context['order']], $query)));
    }

    private function panelIsOpen(string $html, string $tab): bool
    {
        return (bool) preg_match('/x-show="tab === \x27'.$tab.'\x27"\s*>/', $html);
    }

    public function test_each_payment_option_gets_its_own_tab(): void
    {
        $this->enableEverything();
        $context = $this->createOrderContext();

        $html = $this->page($context)
            ->assertOk()
            ->assertSee('How would you like to pay?')
            ->assertSeeInOrder(['data-channel="azampay_mpesa"', 'data-channel="clickpesa_card"', 'data-channel="paypal"', 'data-channel="manual"'], false)
            ->getContent();

        // The first channel opens; the others are hidden until chosen.
        $this->assertTrue($this->panelIsOpen($html, 'azampay_mpesa'));
        foreach (['clickpesa_card', 'paypal', 'manual'] as $tab) {
            $this->assertFalse($this->panelIsOpen($html, $tab), "$tab should start hidden");
        }
    }

    public function test_tab_in_the_url_opens_that_option(): void
    {
        $this->enableEverything();
        $context = $this->createOrderContext();

        $html = $this->page($context, ['tab' => 'paypal'])->getContent();

        $this->assertTrue($this->panelIsOpen($html, 'paypal'));
        $this->assertFalse($this->panelIsOpen($html, 'azampay_mpesa'));

        // Old links (?tab=card) still open the card option.
        $this->assertTrue($this->panelIsOpen($this->page($context, ['tab' => 'card'])->getContent(), 'clickpesa_card'));
    }

    public function test_form_errors_reopen_their_tab(): void
    {
        $this->enableEverything();
        $context = $this->createOrderContext();

        $html = $this->withSession(['_old_input' => ['channel' => 'azampay_airtel']])
            ->page($context, [], ['phone' => 'Enter a valid number'])->getContent();
        $this->assertTrue($this->panelIsOpen($html, 'azampay_airtel'));

        $html = $this->page($context, [], ['card_phone' => 'Enter a valid number'])->getContent();
        $this->assertTrue($this->panelIsOpen($html, 'clickpesa_card'));

        $html = $this->page($context, [], ['payment_proof' => 'Upload a file'])->getContent();
        $this->assertTrue($this->panelIsOpen($html, 'manual'));
    }

    public function test_no_tab_bar_when_only_one_way_to_pay(): void
    {
        PaymentMethod::create(['code' => 'mpesa', 'name' => 'M-Pesa', 'qr_image' => 'payment-methods/mpesa.png', 'enabled' => true, 'sort_order' => 1]);
        $context = $this->createOrderContext();

        $html = $this->page($context)
            ->assertOk()
            ->assertDontSee('How would you like to pay?')
            ->assertSee('Pay with M-Pesa')
            ->getContent();

        $this->assertTrue($this->panelIsOpen($html, 'manual'));
    }

    public function test_alipay_and_wechat_pay_are_their_own_options_first(): void
    {
        PaymentMethod::create(['code' => 'mpesa', 'name' => 'M-Pesa', 'qr_image' => 'payment-methods/mpesa.png', 'enabled' => true, 'sort_order' => 1]);
        PaymentMethod::create(['code' => 'alipay', 'name' => 'Alipay', 'qr_image' => 'payment-methods/alipay.png', 'enabled' => true, 'sort_order' => 2]);
        PaymentMethod::create(['code' => 'wechat_pay', 'name' => 'WeChat Pay', 'qr_image' => 'payment-methods/wechat.png', 'enabled' => true, 'sort_order' => 3]);
        $context = $this->createOrderContext();

        $html = $this->page($context)
            ->assertOk()
            ->assertSeeInOrder(['data-channel="manual_alipay"', 'data-channel="manual_wechat"', 'data-channel="manual"'], false)
            ->getContent();

        // Alipay opens first, with its own QR and the order reference.
        $this->assertTrue($this->panelIsOpen($html, 'manual_alipay'));
        $this->assertStringContainsString('alipay.png', $html);
        $this->assertStringContainsString($context['order']->order_number, $html);

        // "Pay manually" keeps only the other methods.
        preg_match('/data-channel-panel="manual" .*?<\/form>/s', $html, $manual);
        $this->assertStringContainsString('mpesa', $manual[0] ?? '');
        $this->assertStringNotContainsString('wechat_pay', $manual[0] ?? '');

        // A WeChat Pay proof is saved as a WeChat Pay payment.
        Storage::fake('private');
        $this->actingAs($context['user'])->post(route('user.payments.store', $context['order']), [
            'payment_method' => 'wechat_pay',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();
        $this->assertSame('wechat_pay', \App\Models\Payment::firstOrFail()->payment_method);
    }
}
