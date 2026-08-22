<?php

namespace Database\Factories;

use App\Models\SequenceMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SequenceMode>
 */
class SequenceModeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->lexify('?????'),
            'legend_text' => fake()->sentence(),
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
