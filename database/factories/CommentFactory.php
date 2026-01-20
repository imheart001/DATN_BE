<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Blogs;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition()
    {
        return [
            'blogs_id' => Blogs::inRandomOrder()->first()?->id ?? Blogs::factory(),
            'user_name' => fake()->userName(),
            'content' => fake()->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
