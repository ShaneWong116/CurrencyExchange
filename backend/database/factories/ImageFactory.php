<?php

namespace Database\Factories;

use App\Models\Image;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'transaction_id' => null,
            'draft_id' => null,
            'original_name' => $this->faker->word() . '.jpg',
            'file_size' => $this->faker->numberBetween(1000, 100000),
            'mime_type' => 'image/jpeg',
            'width' => $this->faker->numberBetween(100, 1920),
            'height' => $this->faker->numberBetween(100, 1080),
            'file_content' => base64_encode($this->faker->text(100)),
        ];
    }

    public function orphaned(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_id' => null,
            'draft_id' => null,
        ]);
    }
}
