<?php

use App\Enums\TermType;
use App\Livewire\Ocorrencias\Index;
use App\Models\Edition;
use App\Models\Occurrence;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

test('visitantes sao redirecionados para o login', function () {
    $this->get(route('ocorrencias.index'))->assertRedirect(route('login'));
});

test('usuario autenticado abre a pagina de ocorrencias', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('ocorrencias.index'))
        ->assertOk()
        ->assertSee('Ocorrências');
});

test('sem termo selecionado mostra cards dos termos ativos', function () {
    Term::factory()->create(['termo' => 'Maiquinique']);
    Term::factory()->create(['termo' => 'Plenary', 'tipo' => TermType::Empresa]);

    Livewire::test(Index::class)
        ->assertSee('Maiquinique')
        ->assertSee('Plenary')
        ->assertDontSee('Edição');
});

test('clicar no card abre a listagem do termo', function () {
    $termo = Term::factory()->create(['termo' => 'Maiquinique']);
    $edicao = Edition::query()->create(['id' => 50, 'data' => '2026-10-23', 'suplemento' => false]);

    Occurrence::query()->create([
        'edicao_id' => $edicao->id,
        'pagina' => 3,
        'termo_id' => $termo->id,
        'trecho' => 'Trecho da ocorrência de Maiquinique',
        'contexto' => 'Contexto ampliado',
        'atos' => [],
        'processos' => [],
        'tipo' => 'mencao',
        'ocorrencias_na_pagina' => 1,
        'pdf_url' => 'https://example.com.pdf',
        'periodo_inicio' => '2026-10-23',
        'periodo_fim' => '2026-10-23',
        'created_at' => '2026-10-24T00:00:00Z',
    ]);

    Livewire::test(Index::class)
        ->call('abrirTermo', $termo->id)
        ->assertSet('filtroTermo', (string) $termo->id)
        ->assertSee('Voltar')
        ->assertSee('Trecho da ocorrência');
});

test('voltar para os cards limpa o filtro', function () {
    $termo = Term::factory()->create(['termo' => 'Maiquinique']);

    Livewire::test(Index::class)
        ->call('abrirTermo', $termo->id)
        ->assertSet('filtroTermo', (string) $termo->id)
        ->call('voltarCards')
        ->assertSet('filtroTermo', '')
        ->assertSee('Maiquinique');
});

test('filtros por tipo e periodo funcionam na listagem', function () {
    $termo = Term::factory()->create();
    $edicao = Edition::query()->create(['id' => 60, 'data' => '2026-10-01', 'suplemento' => false]);

    Occurrence::query()->create([
        'edicao_id' => $edicao->id,
        'pagina' => 1,
        'termo_id' => $termo->id,
        'trecho' => 'Ato registrado',
        'contexto' => 'ctx',
        'atos' => ['x'],
        'processos' => [],
        'tipo' => 'ato',
        'ocorrencias_na_pagina' => 1,
        'pdf_url' => '',
        'periodo_inicio' => '2026-10-01',
        'periodo_fim' => '2026-10-01',
        'created_at' => '2026-10-02T00:00:00Z',
    ]);

    Occurrence::query()->create([
        'edicao_id' => $edicao->id,
        'pagina' => 2,
        'termo_id' => $termo->id,
        'trecho' => 'Menção simples',
        'contexto' => 'ctx',
        'atos' => [],
        'processos' => [],
        'tipo' => 'mencao',
        'ocorrencias_na_pagina' => 1,
        'pdf_url' => '',
        'periodo_inicio' => '2026-10-01',
        'periodo_fim' => '2026-10-01',
        'created_at' => '2026-10-02T00:00:00Z',
    ]);

    Livewire::test(Index::class)
        ->call('abrirTermo', $termo->id)
        ->set('tipo', 'ato')
        ->assertSee('Ato registrado')
        ->assertDontSee('Menção simples');
});

test('paginacao por cursor ordena pela edicao e nao repete ocorrencias', function () {
    $termo = Term::factory()->create();
    $edicaoAntiga = Edition::query()->create(['id' => 1, 'data' => '2026-09-01', 'suplemento' => false]);
    $edicaoRecente = Edition::query()->create(['id' => 2, 'data' => '2026-10-01', 'suplemento' => false]);

    foreach (range(1, 21) as $dia) {
        Occurrence::query()->create([
            'edicao_id' => $dia === 21 ? $edicaoAntiga->id : $edicaoRecente->id,
            'pagina' => 1,
            'termo_id' => $termo->id,
            'trecho' => 'Trecho '.$dia,
            'contexto' => 'Contexto',
            'atos' => [],
            'processos' => [],
            'tipo' => 'mencao',
            'periodo_inicio' => sprintf('2026-08-%02d', $dia),
            'periodo_fim' => sprintf('2026-08-%02d', $dia),
            'created_at' => '2026-10-24T00:00:00Z',
        ]);
    }

    $listagem = Livewire::test(Index::class)->call('abrirTermo', $termo->id);
    $primeiraPagina = $listagem->instance()->ocorrencias;

    $listagem->call('setPage', $primeiraPagina->nextCursor()->encode(), 'cursor');
    $segundaPagina = $listagem->instance()->ocorrencias;

    expect($primeiraPagina->pluck('edicao_data')->unique()->all())->toBe(['2026-10-01']);
    expect($segundaPagina->pluck('trecho')->all())->toBe(['Trecho 21']);
    expect($primeiraPagina->pluck('id')->intersect($segundaPagina->pluck('id')))->toBeEmpty();
});
