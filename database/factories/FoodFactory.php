<?php

namespace Database\Factories;

use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

class FoodFactory extends Factory
{
    protected $model = Food::class;

    public function definition()
    {
        return [
            'name' => fake()->word() . ' Combo',
            'image' => 'https://placehold.co/300x300?text=Food',
            'price' => (string) fake()->numberBetween(5, 50),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
