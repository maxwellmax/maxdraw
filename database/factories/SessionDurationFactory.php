<?php

namespace Database\Factories;

use App\Models\SessionDuration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionDuration>
 */
class SessionDurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'minutes' => fake()->unique()->numberBetween(5, 240),
            'is_default' => false,
            'position' => fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }

    /**
     * A duração padrão do catálogo: 45 minutos.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'minutes' => 45,
            'is_default' => true,
        ]);
    }
}
