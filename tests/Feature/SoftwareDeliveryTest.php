<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
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

    private function softwareContext(): array
    {
        $product = Product::factory()->create([
            'status' => 'published',
            'type' => 'software',
            'software_version' => 'v1.0',
            'software_key' => 'SHARED-KEY-999',
        ]);

        $plan = Plan::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
            'duration_days' => null,
            'price' => 50000,
        ]);

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

    public function test_approval_opens_the_access_window_with_the_shared_key(): void
    {
        $ctx = $this->softwareContext();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.payments.approve', $ctx['payment']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['id' => $ctx['payment']->id, 'status' => 'approved']);
        $this->assertDatabaseHas('orders', ['id' => $ctx['order']->id, 'status' => 'confirmed']);

        $ctx['order']->refresh();
        $this->assertNotNull($ctx['order']->software_access_expires_at);
        $this->assertTrue($ctx['order']->softwareAccessActive());

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseHas('products', ['id' => $ctx['product']->id, 'software_key' => 'SHARED-KEY-999']);
    }

    public function test_key_is_hidden_before_payment_approval(): void
    {
        $ctx = $this->softwareContext();

        $response = $this->actingAs($ctx['user'])
            ->get(route('user.orders.show', $ctx['order']));

        $response->assertOk();
        $response->assertDontSee('SHARED-KEY-999');
        $response->assertSee('Once your payment is approved');
    }

    public function test_buyer_sees_shared_key_when_access_is_open(): void
    {
        $ctx = $this->softwareContext();
        $this->actingAs($this->admin())->post(route('admin.payments.approve', $ctx['payment']));

        $response = $this->actingAs($ctx['user'])
            ->get(route('user.orders.show', $ctx['order']));

        $response->assertOk();
        $response->assertSee('SHARED-KEY-999');
        $response->assertSee('Access active until');
    }

    public function test_key_hides_after_access_window_passes(): void
    {
        $ctx = $this->softwareContext();
        $this->actingAs($this->admin())->post(route('admin.payments.approve', $ctx['payment']));

        $ctx['order']->update(['software_access_expires_at' => now()->subMinute()]);

        $response = $this->actingAs($ctx['user'])
            ->get(route('user.orders.show', $ctx['order']));

        $response->assertOk();
        $response->assertSee('Access expired');
        $response->assertDontSee('SHARED-KEY-999');
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