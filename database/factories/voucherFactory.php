<?php

namespace Database\Factories;

use App\Models\voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class voucherFactory extends Factory
{
    protected $model = voucher::class;

    public function definition()
    {
        return [
            'code' => fake()->unique()->bothify('###???'),
            'start_time' => now(),
            'end_time' => fake()->dateTimeBetween('now', '+1 month'),
            'usage_limit' => fake()->numberBetween(10, 100),
            'price_voucher' => fake()->numberBetween(5, 50),
            'description' => fake()->sentence(),
            'remaining_limit' => fake()->numberBetween(0, 50),
            'limit' => fake()->numberBetween(1, 2),
            'percent' => fake()->numberBetween(10, 50),
            'minimum_amount' => fake()->numberBetween(50, 100),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
