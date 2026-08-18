<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraphs(3, true),
            'features' => [
                fake()->sentence,
                fake()->sentence,
                fake()->sentence,
            ],
            'price' => fake()->randomFloat(2, 10, 500),
            'status' => fake()->randomElement(['draft', 'published']),
            'is_featured' => fake()->boolean(20),
        ];
    }
}
