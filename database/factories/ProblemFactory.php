<?php

namespace Database\Factories;

use App\Models\Problem;
use App\Models\ProblemLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Problem>
 */
class ProblemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->lexify('????????'),
            'name' => fake()->unique()->words(3, true),
            'tag' => fake()->words(2, true),
            'problem_level_id' => ProblemLevel::factory(),
            'context' => fake()->paragraph(),
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
