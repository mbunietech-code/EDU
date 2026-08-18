<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'image_path' => 'payment-proofs/' . fake()->uuid() . '.jpg',
            'caption' => fake()->sentence(),
        ];
    }
}
