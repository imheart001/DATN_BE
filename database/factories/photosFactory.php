<?php

namespace Database\Factories;

use App\Models\photos;
use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;

class photosFactory extends Factory
{
    protected $model = photos::class;

    public function definition()
    {
        return [
            'film_id' => Film::inRandomOrder()->first()?->id ?? Film::factory(),
            'image' => 'https://placehold.co/400x300?text=Photo',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
