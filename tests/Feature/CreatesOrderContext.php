<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PaymentApprovalService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

trait CreatesOrderContext
{
    protected function createOrderContext(array $overrides = []): array
    {
        $product = Product::factory()->create(['status' => 'published']);
        $plan = Plan::factory()->create([
            'product_id' => $product->id,
            'status' => 'active',
            'duration_days' => 30,
            'price' => 45000,
        ]);

        $accountSpecs = $overrides['accounts'] ?? 1;
        $accountSpecs = is_int($accountSpecs) ? array_fill(0, $accountSpecs, []) : $accountSpecs;

        foreach ($accountSpecs as $accountOverrides) {
            Account::factory()->create(array_merge(
                ['product_id' => $product->id, 'status' => 'available'],
                $accountOverrides
            ));
        }

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => 'pending',
        ]);

        return compact('product', 'plan', 'user', 'order');
    }

    protected function createPendingPayment(Order $order, User $user): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $order->amount,
            'payment_method' => 'mobile_money',
            'transaction_reference' => strtoupper(\Illuminate\Support\Str::random(10)),
            'status' => 'pending',
        ]);
    }
}