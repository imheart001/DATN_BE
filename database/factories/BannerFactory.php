<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition()
    {
        return [
            'title' => fake()->sentence(5),
            'image' => 'https://placehold.co/1200x400?text=Banner',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
