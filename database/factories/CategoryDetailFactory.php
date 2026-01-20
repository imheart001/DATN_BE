<?php

namespace Database\Factories;

use App\Models\CategoryDetail;
use App\Models\Categories;
use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryDetailFactory extends Factory
{
    protected $model = CategoryDetail::class;

    public function definition()
    {
        return [
            'category_id' => Categories::inRandomOrder()->first()?->id ?? Categories::factory(),
            'film_id' => Film::inRandomOrder()->first()?->id ?? Film::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
