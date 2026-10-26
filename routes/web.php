<?php

use App\Livewire\Buscas\Index as BuscasIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Edicoes\Index as EdicoesIndex;
use App\Livewire\Multas\Index as MultasIndex;
use App\Livewire\Ocorrencias\Index as OcorrenciasIndex;
use App\Livewire\Processos\Index as ProcessosIndex;
use App\Livewire\Termos\Index as TermosIndex;
use App\Livewire\Usuarios\Index as UsuariosIndex;
use App\Models\Multa;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');

    Route::livewire('buscas', BuscasIndex::class)->name('buscas.index');

    Route::livewire('ocorrencias', OcorrenciasIndex::class)->name('ocorrencias.index');

    Route::livewire('processos', ProcessosIndex::class)->name('processos.index');

    Route::livewire('edicoes', EdicoesIndex::class)->name('edicoes.index');

    Route::livewire('termos', TermosIndex::class)->name('termos.index');

    Route::livewire('multas', MultasIndex::class)->name('multas.index');

    Route::livewire('usuarios', UsuariosIndex::class)->name('usuarios.index');

    Route::get('multas/{multa}/comprovante', function (Multa $multa) {
        abort_unless($multa->comprovante !== null, 404);
        abort_unless(Storage::disk('local')->exists($multa->comprovante), 404);

        return Storage::disk('local')->download($multa->comprovante);
    })->name('multas.comprovante');
});

require __DIR__.'/settings.php';
