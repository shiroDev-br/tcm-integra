<?php

namespace Database\Factories;

use App\Enums\MultaStatus;
use App\Models\Multa;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Multa>
 */
class MultaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dataMulta = fake()->dateTimeBetween('-18 months', '-1 month');

        return [
            'termo_id' => Term::factory(),
            'gestor' => fake()->name(),
            'processo_numero' => strtoupper(fake()->bothify('??#####')),
            'data_multa' => $dataMulta->format('Y-m-d'),
            'data_vencimento' => $dataMulta->modify('+30 days')->format('Y-m-d'),
            'valor' => fake()->randomFloat(2, 100, 50000),
            'situacao' => MultaStatus::Pendente,
            'comprovante' => null,
        ];
    }

    /**
     * Indicate that the fine has already been paid.
     */
    public function paga(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao' => MultaStatus::Paga,
        ]);
    }
}
