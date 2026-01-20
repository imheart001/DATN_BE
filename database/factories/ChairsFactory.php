<?php

namespace Database\Factories;

use App\Models\Chairs;
use App\Models\TimeDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChairsFactory extends Factory
{
    protected $model = Chairs::class;

    public function definition()
    {
        return [
            'name' => fake()->randomLetter() . fake()->numberBetween(1, 20),
            'price' => (string) fake()->numberBetween(5, 15),
            'id_time_detail' => TimeDetail::inRandomOrder()->first()?->id ?? TimeDetail::factory(),
            'book_ticket_detail' => null, // Optional, can be set if linking to BookTicket
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
