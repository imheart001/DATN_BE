<?php

namespace Database\Factories;

use App\Models\Film;
use App\Models\FilmRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilmReleaseFactory extends Factory
{
    protected $model = FilmRelease::class;

    public function definition()
    {
        $releaseDate = fake()->dateTimeBetween('-1 year', 'now');
        $endDate = fake()->dateTimeBetween('now', '+6 months');

        return [
            'film_id' => Film::inRandomOrder()->first()?->id ?? Film::factory(),
            'release_date' => $releaseDate,
            'end_date' => $endDate,
            'label' => fake()->randomElement(['Khởi chiếu lần 1', 'Khởi chiếu lại', 'Special Screening']),
            'note' => fake()->optional()->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
