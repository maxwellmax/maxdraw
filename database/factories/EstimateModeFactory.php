<?php

namespace Database\Factories;

use App\Models\EstimateMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateMode>
 */
class EstimateModeFactory extends Factory
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
            'slug' => fake()->unique()->lexify('??????'),
            'highlighted_row' => fake()->words(3, true),
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
