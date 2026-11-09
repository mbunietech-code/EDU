<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mbunie VPN is bought on vpn.mbuniehub.com: on this site every VPN link
 * leads to its download page, and no new VPN order is made here.
 */
class VpnSoldOnItsOwnSiteTest extends TestCase
{
    use RefreshDatabase;

    private const STORE = 'https://vpn.mbuniehub.com/download';

    private function vpn(): array
    {
        $product = Product::factory()->create(['status' => 'published', 'type' => 'vpn']);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'price' => 15000, 'mvpn_plan_code' => 'm1']);

        return [$product, $plan];
    }

    public function test_the_vpn_product_page_goes_to_the_vpn_download_page(): void
    {
        [$product] = $this->vpn();

        $this->actingAs($this->customer())->get(route('public.products.show', $product))->assertRedirect(self::STORE);
    }

    public function test_customers_see_a_mbunie_vpn_card_that_opens_the_download_page(): void
    {
        $this->vpn();
        $user = $this->customer();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('Mbunie VPN')->assertSee('href="'.self::STORE.'"', false);

        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Public\BrowseProducts::class)
            ->assertSee('Mbunie VPN')->assertSee('href="'.self::STORE.'"', false);
    }

    private function customer(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_the_vpn_order_page_goes_there_too_and_no_order_is_made(): void
    {
        [$product, $plan] = $this->vpn();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('user.orders.create.plan', ['product' => $product->slug, 'plan' => $plan->id]))
            ->assertRedirect(self::STORE);
        $this->actingAs($user)->post(route('user.orders.store'), ['product_id' => $product->id, 'plan_id' => $plan->id])
            ->assertRedirect(self::STORE);

        $this->assertSame(0, Order::count());
    }

    public function test_the_app_cannot_order_vpn_here_and_is_told_where_to_buy(): void
    {
        [$product, $plan] = $this->vpn();
        Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

        $this->postJson('/api/orders', ['product_id' => $product->id, 'plan_id' => $plan->id])
            ->assertStatus(422)
            ->assertJsonPath('url', self::STORE);

        $this->assertSame(0, Order::count());
    }

    public function test_other_products_are_still_sold_here(): void
    {
        $product = Product::factory()->create(['status' => 'published', 'type' => 'subscription']);
        Plan::factory()->create(['product_id' => $product->id, 'status' => 'active']);

        $this->actingAs($this->customer())->get(route('public.products.show', $product))->assertOk();
    }

    public function test_the_store_address_can_be_changed_in_config(): void
    {
        config(['services.mvpn.store_url' => 'https://vpn.example.test/buy']);
        [$product] = $this->vpn();

        $this->actingAs($this->customer())->get(route('public.products.show', $product))->assertRedirect('https://vpn.example.test/buy');
    }
}
