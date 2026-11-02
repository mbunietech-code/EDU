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
    public function test_each_plan_button_opens_the_order_page_for_that_plan(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'name' => '1 Month', 'sort_order' => 31]);
        $quarter = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'name' => '3 Months', 'sort_order' => 91]);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)->get(route('public.products.show', $product))
            ->assertOk()
            ->assertSee(route('user.orders.create.plan', ['product' => $product->slug, 'plan' => $quarter->id]), false)
            ->assertDontSee('order?plan=', false);

        $this->actingAs($user)
            ->get(route('user.orders.create.plan', ['product' => $product->slug, 'plan' => $quarter->id]))
            ->assertOk()
            ->assertSee('name="plan_id" value="'.$quarter->id.'"', false);
    }

    public function test_old_plan_query_links_still_pick_the_chosen_plan(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'name' => '1 Month', 'sort_order' => 31]);
        $year = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'name' => '1 Year', 'sort_order' => 366]);

        $this->actingAs(\App\Models\User::factory()->create())
            ->get("/products/{$product->slug}/order?plan={$year->id}")
            ->assertOk()
            ->assertSee('name="plan_id" value="'.$year->id.'"', false);
    }
}