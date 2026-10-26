<?php

namespace Database\Factories;

use App\Enums\TermType;
use App\Models\Term;
use App\Services\Clock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'termo' => fake()->unique()->city(),
            'tipo' => TermType::Municipio,
            'ativo' => true,
            'variacoes' => [],
            'created_at' => Clock::nowUtcIso(),
        ];
    }

    /**
     * Indicate that the term is inactive (leaves collection and read results).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'ativo' => false,
        ]);
    }
}
