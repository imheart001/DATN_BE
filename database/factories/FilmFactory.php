<?php

namespace Database\Factories;

use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FilmFactory extends Factory
{
    protected $model = Film::class;

    public function definition()
    {
        $name = fake()->sentence(3);
        $releaseDate = fake()->dateTimeBetween('-1 year', 'now');
        $endDate = fake()->dateTimeBetween('now', '+1 year');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'image' => 'https://placehold.co/400x600?text=Film',
            'poster' => 'https://placehold.co/800x400?text=Poster',
            'limit_age' => fake()->randomElement(['C13', 'C16', 'C18', 'P']),
            'trailer' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'time' => fake()->numberBetween(90, 180) . ' minutes',
            'release_date' => $releaseDate,
            'end_date' => $endDate,
            'description' => fake()->paragraph(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
