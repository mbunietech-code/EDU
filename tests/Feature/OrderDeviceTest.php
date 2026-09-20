<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_SUPER_ADMIN,
            'permissions' => null,
        ]);
    }

    public function test_admin_sets_device_on_order_and_it_shows_on_order_and_subscription_lists(): void
    {
        $this->actingAs($this->superAdmin());

        $subscription = Subscription::factory()->create();
        $order = $subscription->order;

        $this->put(route('admin.orders.update', $order), ['device' => 'iPhone 13'])->assertRedirect();

        $this->assertSame('iPhone 13', $order->fresh()->device);
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('iPhone 13');
        $this->get(route('admin.subscriptions.index'))->assertOk()->assertSee('iPhone 13');
        $this->get(route('admin.subscriptions.show', $subscription))->assertOk()->assertSee('iPhone 13');
    }

    public function test_updating_device_alone_does_not_wipe_payment_instructions(): void
    {
        $this->actingAs($this->superAdmin());
        $order = Order::factory()->create(['payment_instructions' => 'Pay via M-Pesa']);

        $this->put(route('admin.orders.update', $order), ['device' => 'Windows']);

        $this->assertSame('Pay via M-Pesa', $order->fresh()->payment_instructions);
    }

    public function test_device_is_limited_to_100_characters(): void
    {
        $this->actingAs($this->superAdmin());
        $order = Order::factory()->create();

        $this->put(route('admin.orders.update', $order), ['device' => str_repeat('a', 101)])
            ->assertSessionHasErrors('device');
    }
}
