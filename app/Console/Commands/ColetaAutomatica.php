<?php

namespace App\Console\Commands;

use App\Enums\PeriodPreset;
use App\Exceptions\SourceUnavailable;
use App\Models\Configuracao;
use App\Services\Clock;
use App\Services\SearchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tcm:coleta-automatica')]
#[Description('Runs the daily automatic collection (current day only) once the configured time is reached.')]
class ColetaAutomatica extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SearchService $search): int
    {
        $hoje = Clock::today();
        $configuracao = Configuracao::atual();

        if (! $configuracao->coletaPendente($hoje, Clock::currentTime())) {
            return self::SUCCESS;
        }

        // Claimed before collecting so a failing source is not retried every minute.
        if (! $configuracao->reservarColetaDoDia($hoje)) {
            return self::SUCCESS;
        }

        try {
            $search->execute(['periodo' => PeriodPreset::Today->value]);
        } catch (SourceUnavailable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Automatic collection for {$hoje} finished.");

        return self::SUCCESS;
    }
}
