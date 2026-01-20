<?php

namespace Database\Factories;

use App\Models\MovieRoom;
use App\Models\Cinemas;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovieRoomFactory extends Factory
{
    protected $model = MovieRoom::class;

    public function definition()
    {
        return [
            'id_cinema' => Cinemas::inRandomOrder()->first()?->id ?? Cinemas::factory(),
            'name' => 'Screen ' . fake()->numberBetween(1, 10),
            'status' => fake()->randomElement([0, 1]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
