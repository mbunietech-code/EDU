<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => 'AI Account ' . fake()->unique()->numberBetween(1, 1000),
            'description' => fake()->sentence(),
            'credentials' => null,
            'status' => fake()->randomElement(['available', 'assigned', 'maintenance']),
            'metadata' => ['type' => 'api', 'endpoint' => 'https://api.example.com'],
        ];
    }
}
