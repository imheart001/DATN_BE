<?php

namespace Database\Factories;

use App\Models\FilmMaker;
use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilmMakerFactory extends Factory
{
    protected $model = FilmMaker::class;

    public function definition()
    {
        return [
            'type' => fake()->randomElement([1, 2]), // 1: Actor, 2: Director etc.
            'name' => fake()->name(),
            'image' => 'https://placehold.co/200x200?text=FilmMaker',
            'as' => fake()->jobTitle(),
            'film_id' => Film::inRandomOrder()->first()?->id ?? Film::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
