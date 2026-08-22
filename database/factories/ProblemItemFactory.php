<?php

namespace Database\Factories;

use App\Models\Problem;
use App\Models\ProblemItem;
use App\Models\ProblemItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemItem>
 */
class ProblemItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'problem_id' => Problem::factory(),
            'problem_item_type_id' => ProblemItemType::factory(),
            'position' => fake()->unique()->numberBetween(1, 999),
            'content' => fake()->sentence(),
        ];
    }
}
