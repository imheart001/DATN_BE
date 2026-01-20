<?php

namespace Database\Factories;

use App\Models\Contact_infos;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class Contact_infosFactory extends Factory
{
    protected $model = Contact_infos::class;

    public function definition()
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
