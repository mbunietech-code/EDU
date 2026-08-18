<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'payment_method' => fake()->randomElement(['bank_transfer', 'mobile_money', 'cash']),
            'transaction_reference' => strtoupper(Str::random(10)),
            'status' => 'pending',
            'admin_note' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }
}
