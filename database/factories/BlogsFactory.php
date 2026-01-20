<?php

namespace Database\Factories;

use App\Models\Blogs;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogsFactory extends Factory
{
    protected $model = Blogs::class;

    public function definition()
    {
        $title = fake()->sentence();
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'image' => 'https://placehold.co/600x400?text=Blog',
            'content' => fake()->paragraphs(3, true),
            'status' => fake()->randomElement([0, 1]), // Assuming 1 is active
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
