<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentApprovalService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\CreatesOrderContext;
use Tests\TestCase;

class AppDeliveryAndSupportTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    private function softwareOrder(string $status = 'pending'): array
    {
        Storage::fake(config('software.download_disk', 'private'));
        Storage::disk(config('software.download_disk', 'private'))->put('software/app.zip', 'binary');

        $product = Product::factory()->create([
            'status' => 'published', 'type' => 'software', 'software_key' => 'KEY-123',
            'software_file' => 'software/app.zip', 'software_filename' => 'App Setup.zip',
        ]);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'duration_days' => null, 'price' => 50000]);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'plan_id' => $plan->id, 'amount' => 50000, 'status' => $status,
        ]);

        return [$user, $order];
    }

    public function test_software_key_and_download_appear_once_access_opens(): void
    {
        [$user, $order] = $this->softwareOrder();
        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.delivery.state', 'waiting')
            ->assertJsonPath('data.delivery.key', null)
            ->assertJsonPath('data.delivery.download_url', null);

        $order->update(['status' => 'confirmed', 'confirmed_at' => now(), 'software_access_expires_at' => now()->addMinutes(20)]);

        $res = $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.delivery.state', 'open')
            ->assertJsonPath('data.delivery.key', 'KEY-123')
            ->assertJsonPath('data.delivery.file_name', 'App Setup.zip');

        // The signed link downloads without any login (the phone's browser).
        auth()->forgetGuards();
        $this->get($res->json('data.delivery.download_url'))->assertOk()->assertDownload('App Setup.zip');

        // Tampered or unsigned links are refused.
        $this->get(route('signed.orders.download-software', $order))->assertForbidden();
    }

    public function test_expired_software_access_hides_the_key(): void
    {
        [$user, $order] = $this->softwareOrder('confirmed');
        $order->update(['software_access_expires_at' => now()->subMinute()]);
        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertJsonPath('data.delivery.state', 'expired')
            ->assertJsonPath('data.delivery.key', null);
    }

    public function test_subscription_credentials_only_while_active(): void
    {
        $context = $this->createOrderContext(['accounts' => [['credentials' => app(\App\Services\CredentialService::class)->encrypt("user@x.com\npass123")]]]);
        app(PaymentApprovalService::class)->approve($this->createPendingPayment($context['order'], $context['user']));
        $subscription = $context['order']->fresh()->subscription;
        Sanctum::actingAs($context['user']);

        $this->getJson("/api/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.credentials', "user@x.com\npass123");

        $subscription->update(['status' => 'expired']);
        $this->getJson("/api/subscriptions/{$subscription->id}")->assertJsonPath('data.credentials', null);
    }

    public function test_contact_form_from_the_app(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Asha', 'email' => 'asha@example.com', 'subject' => 'Help', 'message' => 'Hello',
        ])->assertCreated();

        $this->assertDatabaseHas('contact_messages', ['email' => 'asha@example.com', 'subject' => 'Help']);
    }

    public function test_forgot_password_sends_a_link_without_revealing_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@example.com']);

        $known = $this->postJson('/api/forgot-password', ['email' => 'real@example.com'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
    }
}
