<?php

namespace Database\Factories;

use App\Models\member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class memberFactory extends Factory
{
    protected $model = member::class;

    public function definition()
    {
        return [
            'id_card' => fake()->bothify('##########'),
            'card_class' => fake()->randomElement([1, 2]),
            'activation_date' => fake()->date(),
            'total_spending' => fake()->numberBetween(1000, 100000),
            'accumulated_points' => fake()->numberBetween(0, 1000),
            'points_used' => fake()->numberBetween(0, 500),
            'usable_points' => fake()->numberBetween(0, 500),
            'id_user' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
