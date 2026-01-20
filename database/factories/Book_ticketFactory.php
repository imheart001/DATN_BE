<?php

namespace Database\Factories;

use App\Models\Book_ticket;
use App\Models\User;
use App\Models\TimeDetail;
use App\Models\Chairs;
use Illuminate\Database\Eloquent\Factories\Factory;

class Book_ticketFactory extends Factory
{
    protected $model = Book_ticket::class;

    public function definition()
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'id_time_detail' => TimeDetail::inRandomOrder()->first()?->id ?? TimeDetail::factory(),
            'id_chair' => Chairs::inRandomOrder()->first()?->id ?? Chairs::factory(),
            'payment' => fake()->randomElement([1, 2, 3]),
            'amount' => fake()->numberBetween(1, 5),
            'time' => fake()->time('H:i'),
            'id_staff_check' => 0,
            'id_code' => fake()->bothify('TK-#####'),
            'status' => fake()->randomElement([0, 1]),
            'discount_voucher' => fake()->numberBetween(0, 50),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
