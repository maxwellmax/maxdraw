<?php

namespace Database\Factories;

use App\Models\ProblemLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemLevel>
 */
class ProblemLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->lexify('??????'),
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
