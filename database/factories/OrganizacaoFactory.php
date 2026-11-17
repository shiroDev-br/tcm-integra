<?php

namespace Database\Factories;

use App\Enums\OrganizacaoStatus;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organizacao>
 */
class OrganizacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entidade' => 'CM de '.fake()->city(),
            'status' => OrganizacaoStatus::Pendente,
            'arquivo_saida' => null,
            'avisos' => null,
            'erro' => null,
        ];
    }

    /**
     * Indicate that the organization finished with a zip ready to download.
     */
    public function concluida(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizacaoStatus::Concluida,
            'arquivo_saida' => 'organizacoes/saida.zip',
            'avisos' => [],
            'iniciado_em' => now()->subMinute(),
            'concluido_em' => now(),
        ]);
    }

    /**
     * Indicate that the organization failed.
     */
    public function falhou(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizacaoStatus::Falhou,
            'erro' => 'Nenhum PDF encontrado nos arquivos enviados.',
            'concluido_em' => now(),
        ]);
    }
}
