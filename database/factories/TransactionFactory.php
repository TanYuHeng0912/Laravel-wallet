<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => $this->faker->randomElement(['deposit', 'withdrawal', 'rebate']),
            'amount' => number_format($this->faker->randomFloat(2, 1, 1000), 2, '.', ''),
        ];
    }
}
