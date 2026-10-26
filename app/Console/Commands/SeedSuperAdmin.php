<?php

namespace App\Console\Commands;

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tcm:seed-superadmin')]
#[Description('Seeds the TCM superadmin account once (TCM_SUPERADMIN_SENHA).')]
class SeedSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SuperAdminSeeder $seeder): int
    {
        if ((string) config('tcm.superadmin_password') === '') {
            $this->warn('TCM_SUPERADMIN_SENHA empty: no superadmin account created.');

            return self::SUCCESS;
        }

        $criado = $seeder->seed();

        $this->info($criado
            ? sprintf("superadmin '%s' created.", config('tcm.superadmin_user'))
            : 'superadmin already exists (nothing to do).');

        return self::SUCCESS;
    }
}
