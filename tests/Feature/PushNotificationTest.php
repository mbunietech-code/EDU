<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Product;
use App\Models\User;
use App\Notifications\Admin\NewOrder;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_token_can_be_registered_and_removed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/device-tokens', [
            'token' => 'fcm-abc-123',
            'platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-abc-123',
            'platform' => 'android',
        ]);

        // Re-registering the same token just updates it.
        $this->postJson('/api/device-tokens', ['token' => 'fcm-abc-123'])->assertOk();
        $this->assertSame(1, DeviceToken::where('token', 'fcm-abc-123')->count());

        $this->deleteJson('/api/device-tokens', ['token' => 'fcm-abc-123'])->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-abc-123']);
    }

    public function test_placing_an_order_does_not_error_when_fcm_is_not_configured(): void
    {
        config(['services.fcm.enabled' => false]);

        $buyer = User::factory()->create();
        $product = Product::factory()->create(['status' => 'published']);
        $order = $buyer->orders()->create([
            'order_number' => 'MBT-TEST99',
            'product_id' => $product->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        // Fires user + admin database notifications -> PushDatabaseNotification listener.
        app(NotificationService::class)->notifyOrderCreated($buyer, $order);

        $this->assertSame(1, $buyer->notifications()->count());
    }

    public function test_notifications_endpoint_returns_the_users_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new NewOrder(
            $user->orders()->create([
                'order_number' => 'MBT-N1',
                'product_id' => Product::factory()->create()->id,
                'amount' => 500,
                'status' => 'pending',
            ])
        ));

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/notifications')->assertOk();
        $res->assertJsonPath('meta.unread', 1);
        $this->assertNotEmpty($res->json('data'));

        $id = $res->json('data.0.id');
        $this->postJson("/api/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('meta.unread', 0);
    }
}
