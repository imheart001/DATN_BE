<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition()
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'content' => fake()->sentence(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
