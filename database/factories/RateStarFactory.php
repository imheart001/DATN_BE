<?php

namespace Database\Factories;

use App\Models\RateStar;
use App\Models\User;
use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;

class RateStarFactory extends Factory
{
    protected $model = RateStar::class;

    public function definition()
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'film_id' => Film::inRandomOrder()->first()?->id ?? Film::factory(),
            'comment' => fake()->sentence(),
            'star_rating' => fake()->numberBetween(1, 5),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
