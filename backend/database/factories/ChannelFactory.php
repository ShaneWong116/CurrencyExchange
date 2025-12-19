<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company() . ' Channel',
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'label' => $this->faker->randomElement(['primary', 'secondary']),
            'category' => $this->faker->randomElement(['alipay', 'wechat', 'bank']),
            'status' => 'active',
            'transaction_count' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
