<?php

namespace Database\Factories;

use App\Models\ProblemItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemItemType>
 */
class ProblemItemTypeFactory extends Factory
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
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
