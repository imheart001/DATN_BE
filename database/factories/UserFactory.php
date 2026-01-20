<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'id_cinema' => null, // Optional, can be set if needed
            'image' => 'https://placehold.co/200x200?text=User',
            'phone' => fake()->phoneNumber(),
            'role' => fake()->randomElement([0, 1]), // 0: User, 1: Admin
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // password
            'date_of_birth' => fake()->date(),
            'coin' => fake()->numberBetween(0, 1000),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
