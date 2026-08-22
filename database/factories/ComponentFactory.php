<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\ComponentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Component>
 */
class ComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->lexify('????????');

        return [
            'component_category_id' => ComponentCategory::factory(),
            'slug' => $slug,
            'name' => fake()->unique()->words(2, true),
            'short_name' => fake()->word(),
            'icon_key' => $slug,
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
