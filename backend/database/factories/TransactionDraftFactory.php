<?php

namespace Database\Factories;

use App\Models\TransactionDraft;
use App\Models\Channel;
use App\Models\FieldUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionDraftFactory extends Factory
{
    protected $model = TransactionDraft::class;

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
            'status' => 'draft',
        ];
    }
}
