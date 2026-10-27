<?php

use App\Models\Configuracao;
use App\Services\Clock;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Checked inside schedule:run (one primary-key read), so the command process only starts
// once a day; the daily claim in the command already prevents overlapping runs.
Schedule::command('tcm:coleta-automatica')
    ->everyMinute()
    ->when(fn (): bool => Configuracao::atual()->coletaPendente(Clock::today(), Clock::currentTime()));
