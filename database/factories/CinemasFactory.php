<?php

namespace Database\Factories;

use App\Models\Cinemas;
use Illuminate\Database\Eloquent\Factories\Factory;

class CinemasFactory extends Factory
{
    protected $model = Cinemas::class;

    public function definition()
    {
        return [
            'name' => fake()->company() . ' Cinema',
            'address' => fake()->address(),
            'status' => fake()->randomElement([0, 1]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
