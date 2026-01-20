<?php

namespace Database\Factories;

use App\Models\UsedVoucher;
use App\Models\User;
use App\Models\voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsedVoucherFactory extends Factory
{
    protected $model = UsedVoucher::class;

    public function definition()
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'voucher_code' => voucher::inRandomOrder()->first()?->code ?? fake()->bothify('###???'),
            'used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
