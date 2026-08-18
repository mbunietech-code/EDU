<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase, CreatesOrderContext;

    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create(['product_id' => $product->id]);

        $response = $this->post('/orders', [
            'product_id' => $product->id,
            'plan_id' => $plan->id,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_order(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'price' => 10000]);

        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post('/orders', [
            'product_id' => $product->id,
            'plan_id' => $plan->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $order = Order::first();
        $this->assertEquals($user->id, $order->user_id);
        $this->assertEquals(10000, (float) $order->amount);
        $this->assertEquals('pending', $order->status);
        $this->assertMatchesRegularExpression('/^MBT-[A-Z0-9]{6}$/', $order->order_number);
    }

    public function test_cannot_order_unpublished_product(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active']);

        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post('/orders', [
            'product_id' => $product->id,
            'plan_id' => $plan->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cannot_use_plan_from_a_different_product(): void
    {
        $productA = Product::factory()->create(['status' => 'published']);
        $productB = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create(['product_id' => $productA->id, 'status' => 'active']);

        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post('/orders', [
            'product_id' => $productB->id,
            'plan_id' => $plan->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_plan_cannot_be_ordered(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'inactive']);

        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post('/orders', [
            'product_id' => $product->id,
            'plan_id' => $plan->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_number_is_unique(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active']);
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->post('/orders', ['product_id' => $product->id, 'plan_id' => $plan->id]);
        $this->actingAs($user)->post('/orders', ['product_id' => $product->id, 'plan_id' => $plan->id]);

        $this->assertDatabaseCount('orders', 2);
        $this->assertEquals(2, Order::distinct('order_number')->count());
    }

    public function test_user_cannot_view_someone_elses_order(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active']);
        $owner = \App\Models\User::factory()->create();
        $other = \App\Models\User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($other)->get("/my-orders/{$order->id}");
        $response->assertForbidden();
    }
}