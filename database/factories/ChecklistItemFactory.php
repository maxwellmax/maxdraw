<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory(),
            'position' => fake()->unique()->numberBetween(1, 999),
            'content' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
