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

test('historico mostra os cadastros encontrados em cada coleta', function () {
    SearchRun::query()->create([
        'periodo_inicio' => '2026-11-10',
        'periodo_fim' => '2026-11-10',
        'termos' => 2,
        'status' => SearchStatus::Ok,
        'termos_encontrados' => [['termo_id' => 7, 'termo' => 'Itarantim', 'ocorrencias' => 3, 'processos' => 2]],
        'created_at' => now(),
    ]);
    SearchRun::query()->create([
        'periodo_inicio' => '2026-11-09',
        'periodo_fim' => '2026-11-09',
        'termos' => 2,
        'status' => SearchStatus::Ok,
        'termos_encontrados' => [],
        'created_at' => now()->subDay(),
    ]);

    Livewire::test(Index::class)
        ->assertSee('Itarantim (3)')
        ->assertSee(route('ocorrencias.index', ['termo' => 7]))
        ->assertSee('Nenhum cadastro encontrado');
});

test('aviso lista os cadastros encontrados ou informa que nenhum foi', function () {
    expect(SearchRun::avisoDosEncontrados([]))->toBe('Nenhum cadastro encontrado no Diário Oficial.')
        ->and(SearchRun::avisoDosEncontrados([
            ['termo_id' => 1, 'termo' => 'Boa Nova', 'ocorrencias' => 1, 'processos' => 0],
            ['termo_id' => 2, 'termo' => 'Itarantim', 'ocorrencias' => 4, 'processos' => 2],
        ]))->toBe('Cadastros encontrados: Boa Nova (1), Itarantim (4).');
});
