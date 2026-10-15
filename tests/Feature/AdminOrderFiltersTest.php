<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminOrderFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $vpn;
    private Product $chat;
    private Plan $monthly;
    private Plan $yearly;
    private User $amina;
    private User $baraka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(array_merge(
            ['is_admin' => true],
            Schema::hasColumn('users', 'role') ? ['role' => 'super_admin'] : []
        ));
        $this->vpn = Product::factory()->create(['name' => 'Mbunie VPN', 'status' => 'published', 'type' => 'vpn']);
        $this->chat = Product::factory()->create(['name' => 'ChatPro', 'status' => 'published']);
        $this->monthly = Plan::factory()->create(['product_id' => $this->chat->id, 'name' => 'Monthly', 'status' => 'active']);
        $this->yearly = Plan::factory()->create(['product_id' => $this->chat->id, 'name' => 'Yearly', 'status' => 'active']);
        $this->amina = User::factory()->create(['name' => 'Amina']);
        $this->baraka = User::factory()->create(['name' => 'Baraka']);
    }

    private function order(array $attrs): Order
    {
        return Order::factory()->create(array_merge([
            'user_id' => $this->amina->id,
            'product_id' => $this->chat->id,
            'plan_id' => $this->monthly->id,
            'amount' => 10000,
            'status' => 'pending',
            'device' => null,
        ], $attrs));
    }

    private function list(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.orders.index', $query))->assertOk();
    }

    public function test_renewals_are_marked_and_can_be_filtered(): void
    {
        $original = $this->order(['order_number' => 'MBT-ORIG01', 'status' => 'confirmed']);
        $renewal = $this->order(['order_number' => 'MBT-RENEW1', 'renews_order_id' => $original->id]);
        $this->order(['order_number' => 'MBT-OTHER1']);

        $this->list()->assertSee('Renewal')->assertSee('of')->assertSee('MBT-ORIG01');

        $this->list(['type' => 'renewal'])->assertSee('MBT-RENEW1')->assertDontSee('MBT-OTHER1');
        $this->list(['type' => 'new'])->assertSee('MBT-OTHER1')->assertDontSee('MBT-RENEW1');

        $this->actingAs($this->admin)->get(route('admin.orders.show', $renewal))
            ->assertOk()->assertSee('This order renews')->assertSee('MBT-ORIG01');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $original))
            ->assertOk()->assertSee('Renewal waiting for payment')->assertSee('MBT-RENEW1');

        $renewal->update(['status' => 'confirmed']);
        $this->actingAs($this->admin)->get(route('admin.orders.show', $original))
            ->assertOk()->assertSee('The customer renewed this order with');
        $this->list(['status' => 'renewed'])->assertSee('MBT-ORIG01')->assertDontSee('MBT-OTHER1');
    }

    public function test_clicking_user_product_plan_and_device_filters_the_list(): void
    {
        $this->order(['order_number' => 'MBT-AMINA1', 'device' => 'iPhone 13']);
        $this->order(['order_number' => 'MBT-BARAK1', 'user_id' => $this->baraka->id, 'plan_id' => $this->yearly->id]);
        $this->order(['order_number' => 'MBT-VPNORD', 'product_id' => $this->vpn->id, 'plan_id' => null]);

        // Values are links that add their filter.
        $this->list()
            ->assertSee(route('admin.orders.index', ['user' => $this->baraka->id]), false)
            ->assertSee('device=iPhone%2013', false);

        $this->list(['user' => $this->baraka->id])
            ->assertSee('MBT-BARAK1')->assertDontSee('MBT-AMINA1')->assertSee('User: Baraka');
        $this->list(['product' => $this->vpn->id])
            ->assertSee('MBT-VPNORD')->assertDontSee('MBT-AMINA1');
        $this->list(['product' => $this->chat->id, 'plan' => $this->yearly->id])
            ->assertSee('MBT-BARAK1')->assertDontSee('MBT-AMINA1')->assertSee('Plan: Yearly');
        $this->list(['device' => 'iPhone 13'])
            ->assertSee('MBT-AMINA1')->assertDontSee('MBT-BARAK1');
        $this->list(['device' => 'none'])
            ->assertSee('MBT-BARAK1')->assertDontSee('MBT-AMINA1');
    }

    public function test_status_filters_follow_the_badges(): void
    {
        $active = $this->order(['order_number' => 'MBT-ACTIVE', 'status' => 'confirmed']);
        $expired = $this->order(['order_number' => 'MBT-EXPIRD', 'status' => 'confirmed']);
        $this->order(['order_number' => 'MBT-PENDNG']);
        foreach ([[$active, 10], [$expired, -5]] as [$o, $days]) {
            Subscription::create([
                'user_id' => $o->user_id, 'product_id' => $o->product_id, 'plan_id' => $o->plan_id, 'order_id' => $o->id,
                'start_date' => now()->subDays(30), 'expiry_date' => now()->addDays($days),
                'status' => $days > 0 ? 'active' : 'expired',
            ]);
        }
        $vpnExpired = $this->order(['order_number' => 'MBT-VPNEXP', 'product_id' => $this->vpn->id, 'status' => 'confirmed']);
        $vpnExpired->forceFill(['vpn_activated_at' => now()->subDays(40), 'vpn_expires_at' => now()->subDays(10)])->save();

        $this->list(['status' => 'active'])
            ->assertSee('MBT-ACTIVE')->assertDontSee('MBT-EXPIRD')->assertDontSee('MBT-VPNEXP')->assertDontSee('MBT-PENDNG');
        $this->list(['status' => 'expired'])
            ->assertSee('MBT-EXPIRD')->assertSee('MBT-VPNEXP')->assertDontSee('MBT-ACTIVE');
        $this->list(['status' => 'pending'])
            ->assertSee('MBT-PENDNG')->assertDontSee('MBT-ACTIVE');
    }

    public function test_search_stays_inside_the_other_filters_and_amount_sorts(): void
    {
        $this->order(['order_number' => 'MBT-CHEAP1', 'amount' => 5000, 'status' => 'pending']);
        $this->order(['order_number' => 'MBT-DEAR01', 'amount' => 90000, 'status' => 'cancelled']);

        // Searching by user name must not bring back orders the status filter excluded.
        $this->list(['search' => 'Amina', 'status' => 'pending'])
            ->assertSee('MBT-CHEAP1')->assertDontSee('MBT-DEAR01');

        $html = $this->list(['sort' => 'amount_desc'])->getContent();
        $this->assertLessThan(strpos($html, 'MBT-CHEAP1'), strpos($html, 'MBT-DEAR01'));
        $html = $this->list(['sort' => 'amount_asc'])->getContent();
        $this->assertLessThan(strpos($html, 'MBT-DEAR01'), strpos($html, 'MBT-CHEAP1'));
    }

    public function test_bad_filter_values_are_ignored(): void
    {
        $this->order(['order_number' => 'MBT-ANY001']);

        $this->list(['status' => 'drop table', 'user' => 'x', 'product' => '1 or 1', 'sort' => 'nope'])
            ->assertSee('MBT-ANY001');
    }
}
