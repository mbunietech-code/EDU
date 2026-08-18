<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SoftwareDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function softwareContext(array $keys = ['SOFT-KEY-001']): array
    {
        $product = Product::factory()->create([
            'status' => 'published',
            'type' => 'software',
            'software_version' => 'v1.0',
        ]);

        $plan = Plan::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
            'duration_days' => null,
            'price' => 50000,
        ]);

        foreach ($keys as $keyValue) {
            ProductKey::create([
                'product_id' => $product->id,
                'key_value' => $keyValue,
                'status' => 'available',
            ]);
        }

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => 'pending',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $order->amount,
            'payment_method' => 'yas',
            'transaction_reference' => strtoupper(\Illuminate\Support\Str::random(10)),
            'status' => 'pending',
        ]);

        return compact('product', 'plan', 'user', 'order', 'payment');
    }

    public function test_approval_assigns_a_key_and_opens_access_window(): void
    {
        $ctx = $this->softwareContext();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.payments.approve', $ctx['payment']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['id' => $ctx['payment']->id, 'status' => 'approved']);
        $this->assertDatabaseHas('orders', ['id' => $ctx['order']->id, 'status' => 'confirmed']);

        $key = ProductKey::where('product_id', $ctx['product']->id)->first();
        $this->assertEquals('sold', $key->status);
        $this->assertEquals($ctx['order']->id, $key->order_id);

        $ctx['order']->refresh();
        $this->assertNotNull($ctx['order']->software_access_expires_at);
        $this->assertTrue($ctx['order']->softwareAccessActive());

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseHas('product_keys', ['id' => $key->id, 'status' => 'sold', 'order_id' => $ctx['order']->id]);
    }

    public function test_approval_fails_and_rolls_back_when_no_key_available(): void
    {
        $ctx = $this->softwareContext([]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.payments.approve', $ctx['payment']));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['id' => $ctx['payment']->id, 'status' => 'pending']);
        $this->assertDatabaseHas('orders', ['id' => $ctx['order']->id, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_buyer_sees_key_when_access_is_open(): void
    {
        $ctx = $this->softwareContext();
        $this->actingAs($this->admin())->post(route('admin.payments.approve', $ctx['payment']));

        $response = $this->actingAs($ctx['user'])
            ->get(route('user.orders.show', $ctx['order']));

        $response->assertOk();
        $response->assertSee('SOFT-KEY-001');
        $response->assertSee('Access active until');
    }

    public function test_access_locks_after_access_window_passes(): void
    {
        $ctx = $this->softwareContext();
        $this->actingAs($this->admin())->post(route('admin.payments.approve', $ctx['payment']));

        $ctx['order']->update(['software_access_expires_at' => now()->subMinute()]);

        $response = $this->actingAs($ctx['user'])
            ->get(route('user.orders.show', $ctx['order']));

        $response->assertOk();
        $response->assertSee('Access expired');
        $response->assertDontSee('SOFT-KEY-001');
    }

    public function test_download_requires_active_access(): void
    {
        Storage::fake('private');

        $file = UploadedFile::fake()->create('installer.zip', 200);

        $product = Product::factory()->create([
            'status' => 'published',
            'type' => 'software',
            'software_file' => $file->store('software', 'private'),
            'software_filename' => 'installer.zip',
        ]);

        $plan = Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'duration_days' => null]);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        // Not confirmed yet -> locked
        $this->actingAs($user)->get(route('user.orders.download-software', $order))
            ->assertRedirect();
    }

    public function test_download_succeeds_within_access_window(): void
    {
        Storage::fake('private');

        $file = UploadedFile::fake()->create('installer.zip', 200);

        $product = Product::factory()->create([
            'status' => 'published',
            'type' => 'software',
            'software_file' => $file->store('software', 'private'),
            'software_filename' => 'installer.zip',
        ]);

        Plan::factory()->create(['product_id' => $product->id, 'status' => 'active', 'duration_days' => null]);
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => Plan::where('product_id', $product->id)->first()->id,
            'status' => 'confirmed',
            'software_access_expires_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($user)->get(route('user.orders.download-software', $order))
            ->assertOk()
            ->assertDownload('installer.zip');
    }
}