<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_show_period_totals_and_revenue_per_item(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $buyer = User::factory()->create();
        $product = Product::create(['name' => 'SPSS', 'slug' => 'spss', 'type' => 'software', 'status' => 'published', 'price' => 50000, 'software_key' => 'K']);
        $tool = Tool::create(['name' => 'EndNote', 'slug' => 'endnote', 'price' => 20000, 'status' => 'published']);

        foreach ([[$product->id, null, 50000], [null, $tool->id, 20000]] as $i => [$productId, $toolId, $amount]) {
            $order = Order::create([
                'user_id' => $buyer->id, 'order_number' => 'MBT-T'.$i, 'product_id' => $productId,
                'tool_id' => $toolId, 'amount' => $amount, 'status' => 'confirmed',
            ]);
            Payment::create(['order_id' => $order->id, 'user_id' => $buyer->id, 'amount' => $amount, 'payment_method' => 'manual', 'status' => 'approved']);
        }

        $this->getJson('/api/admin/reports?months=12')->assertOk()
            ->assertJsonPath('months', 12)
            ->assertJsonPath('totals.revenue', 70000)
            ->assertJsonPath('totals.orders', 2)
            ->assertJsonPath('products.0.revenue', 50000)
            ->assertJsonPath('products.0.status', 'published')
            ->assertJsonPath('tools.0.revenue', 20000);

        // The period is kept within 1..24 months.
        $this->getJson('/api/admin/reports?months=99')->assertOk()->assertJsonPath('months', 24);
    }
}
