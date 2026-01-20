<?php

namespace Database\Factories;

use App\Models\Food_ticket_detail;
use App\Models\Book_ticket;
use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

class Food_ticket_detailFactory extends Factory
{
    protected $model = Food_ticket_detail::class;

    public function definition()
    {
        return [
            'book_ticket_id' => Book_ticket::inRandomOrder()->first()?->id ?? Book_ticket::factory(),
            'food_id' => Food::inRandomOrder()->first()?->id ?? Food::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
