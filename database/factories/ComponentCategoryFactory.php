<?php

namespace Database\Factories;

use App\Models\ComponentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentCategory>
 */
class ComponentCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->lexify('??????');

        return [
            'name' => fake()->unique()->word(),
            'slug' => $slug,
            'color_token' => '--c-'.$slug,
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
