<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\PanelUser;
use App\Services\Clock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seeds the superadmin account once (never recreates or changes it).
     */
    public function run(): void
    {
        $this->seed();
    }

    /**
     * Returns true when the account was created by this call.
     */
    public function seed(): bool
    {
        $senha = (string) config('tcm.superadmin_password');

        if ($senha === '') {
            return false;
        }

        $username = (string) config('tcm.superadmin_user');

        if (PanelUser::query()->where('username', $username)->exists()) {
            return false;
        }

        PanelUser::query()->create([
            'username' => $username,
            'senha' => Hash::make($senha),
            'role' => Role::SuperAdmin,
            'ativo' => true,
            'tentativas' => 0,
            'bloqueado_ate' => null,
            'criado_em' => Clock::nowUtcIso(),
        ]);

        return true;
    }
}
