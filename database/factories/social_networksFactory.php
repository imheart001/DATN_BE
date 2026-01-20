<?php

namespace Database\Factories;

use App\Models\social_networks;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class social_networksFactory extends Factory
{
    protected $model = social_networks::class;

    public function definition()
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'type' => fake()->randomElement([1, 2, 3]),
            'social_id' => fake()->uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
