<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class WebSuperAdminSeeder extends Seeder
{
    /**
     * Seeds the dashboard login (superadmin / superadmin) once — the field is
     * kept as `email` for Fortify, but the form accepts the plain username.
     */
    public function run(): void
    {
        $usuario = User::query()->firstOrCreate(
            ['email' => 'superadmin'],
            ['name' => 'superadmin', 'password' => 'superadmin', 'role' => Role::SuperAdmin],
        );

        if ($usuario->email_verified_at === null || $usuario->role !== Role::SuperAdmin) {
            $usuario->forceFill([
                'email_verified_at' => $usuario->email_verified_at ?? now(),
                'role' => Role::SuperAdmin,
            ])->save();
        }
    }
}
