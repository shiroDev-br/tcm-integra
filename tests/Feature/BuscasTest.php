<?php

use App\Enums\SearchStatus;
use App\Livewire\Buscas\Index;
use App\Models\SearchRun;
use Livewire\Livewire;

test('historico exibe a situacao de cada coleta com rotulo legivel', function () {
    foreach ([SearchStatus::Ok, SearchStatus::Partial, SearchStatus::Error] as $status) {
        SearchRun::query()->create([
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-07',
            'termos' => 2,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    Livewire::test(Index::class)
        ->assertSee('Concluída')
        ->assertSee('Parcial')
        ->assertSee('Erro');
});

test('historico vazio orienta a iniciar uma coleta', function () {
    Livewire::test(Index::class)
        ->assertSee('Nenhuma busca registrada');
});
