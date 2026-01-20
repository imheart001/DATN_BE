<?php

namespace Database\Factories;

use App\Models\Categories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoriesFactory extends Factory
{
    protected $model = Categories::class;

    public function definition()
    {
        $name = fake()->unique()->word();
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'status' => fake()->randomElement([0, 1]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
