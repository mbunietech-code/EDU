<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'plan_id' => Plan::factory(),
            'account_id' => Account::factory(),
            'order_id' => Order::factory(),
            'start_date' => now(),
            'expiry_date' => now()->addDays(30),
            'status' => 'active',
        ];
    }
}
