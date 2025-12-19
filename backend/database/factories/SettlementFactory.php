<?php

namespace Database\Factories;

use App\Models\Settlement;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    public function definition(): array
    {
        return [
            'settlement_date' => $this->faker->date(),
            'previous_capital' => $this->faker->randomFloat(2, 100000, 1000000),
            'previous_hkd_balance' => $this->faker->randomFloat(2, 100000, 1000000),
            'profit' => $this->faker->randomFloat(3, 0, 10000),
            'outgoing_profit' => $this->faker->randomFloat(3, 0, 5000),
            'instant_profit' => $this->faker->randomFloat(3, 0, 5000),
            'other_expenses_total' => $this->faker->randomFloat(2, 0, 1000),
            'other_incomes_total' => $this->faker->randomFloat(2, 0, 1000),
            'new_capital' => $this->faker->randomFloat(2, 100000, 1000000),
            'new_hkd_balance' => $this->faker->randomFloat(2, 100000, 1000000),
            'settlement_rate' => $this->faker->randomFloat(5, 0.9, 1.1),
            'rmb_balance_total' => $this->faker->randomFloat(2, 100000, 1000000),
            'sequence_number' => $this->faker->unique()->numberBetween(1, 1000),
        ];
    }
}
