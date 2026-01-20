<?php

namespace Database\Factories;

use App\Models\TimeDetail;
use App\Models\Time;
use App\Models\Film;
use App\Models\MovieRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeDetailFactory extends Factory
{
    protected $model = TimeDetail::class;

    public function definition()
    {
        return [
            'date' => fake()->dateTimeBetween('now', '+1 month'),
            'time_id' => Time::inRandomOrder()->first()?->id ?? Time::factory(),
            'film_id' => Film::inRandomOrder()->first()?->id ?? Film::factory(),
            'room_id' => MovieRoom::inRandomOrder()->first()?->id ?? MovieRoom::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
