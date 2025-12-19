<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Channel;
use App\Models\FieldUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['income', 'outcome']);
        $rmbAmount = $this->faker->randomFloat(2, 100, 10000);
        $exchangeRate = $this->faker->randomFloat(5, 0.9, 1.1);
        $hkdAmount = round($rmbAmount / $exchangeRate, 2);

        return [
            'uuid' => Str::uuid(),
            'user_id' => FieldUser::factory(),
            'channel_id' => Channel::factory(),
            'type' => $type,
            'rmb_amount' => $rmbAmount,
            'hkd_amount' => $hkdAmount,
            'exchange_rate' => $exchangeRate,
            'status' => 'completed',
            'settlement_status' => 'unsettled',
            'submit_time' => now(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
        ]);
    }

    public function outcome(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'outcome',
        ]);
    }

    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'settlement_status' => 'settled',
        ]);
    }

    public function unsettled(): static
    {
        return $this->state(fn (array $attributes) => [
            'settlement_status' => 'unsettled',
        ]);
    }
}
