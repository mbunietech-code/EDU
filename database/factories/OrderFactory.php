<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => strtoupper(Str::random(8)),
            'product_id' => Product::factory(),
            'plan_id' => Plan::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'status' => 'pending',
            'payment_instructions' => null,
            'confirmed_at' => null,
        ];
    }
}
