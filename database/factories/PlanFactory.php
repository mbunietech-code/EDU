<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->word() . ' Plan',
            'description' => fake()->sentence(),
            'duration_days' => fake()->randomElement([7, 30, 60, 90, 180, 365]),
            'price' => fake()->randomFloat(2, 5, 200),
            'status' => 'active',
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
