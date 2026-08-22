<?php

namespace Database\Factories;

use App\Models\LinkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkType>
 */
class LinkTypeFactory extends Factory
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
            'badge_label' => fake()->lexify('?????'),
            'dash_array' => null,
            'is_bidirectional_default' => false,
            'gloss' => fake()->sentence(),
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the link type is drawn as a dashed arrow.
     */
    public function dashed(string $dashArray = '5 4.5'): static
    {
        return $this->state(fn (array $attributes): array => [
            'dash_array' => $dashArray,
        ]);
    }

    /**
     * Indicate that the link type is bidirectional by default, like `ws`.
     */
    public function bidirectional(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_bidirectional_default' => true,
        ]);
    }
}
