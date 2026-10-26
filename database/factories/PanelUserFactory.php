<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\PanelUser;
use App\Services\Clock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<PanelUser>
 */
class PanelUserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $senha;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'senha' => static::$senha ??= Hash::make('password'),
            'role' => Role::Admin,
            'ativo' => true,
            'tentativas' => 0,
            'bloqueado_ate' => null,
            'criado_em' => Clock::nowUtcIso(),
        ];
    }

    /**
     * Indicate that the account is a superadmin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::SuperAdmin,
        ]);
    }

    /**
     * Indicate that the account is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'ativo' => false,
        ]);
    }
}
