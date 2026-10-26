<?php

use App\Livewire\Edicoes\Index;
use App\Models\Edition;
use App\Models\Occurrence;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

/**
 * Edition where an active term was found (only those are listed).
 *
 * @param  array<string, mixed>  $atributos
 */
function edicaoComTermo(array $atributos, ?Term $termo = null): Edition
{
    $edicao = Edition::query()->create($atributos);
    $termo ??= Term::query()->first() ?? Term::factory()->create(['termo' => 'Maiquinique']);

    Occurrence::query()->create([
        'edicao_id' => $edicao->id,
        'pagina' => 1,
        'termo_id' => $termo->id,
        'trecho' => $termo->termo,
        'contexto' => $termo->termo,
        'atos' => [],
        'processos' => [],
        'tipo' => 'mencao',
        'periodo_inicio' => $edicao->data,
        'periodo_fim' => $edicao->data,
        'created_at' => '2026-10-26T12:00:00+00:00',
    ]);

    return $edicao;
}

test('visitantes sao redirecionados para o login', function () {
    $this->get(route('edicoes.index'))->assertRedirect(route('login'));
});

test('usuario autenticado abre a listagem de edicoes', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('edicoes.index'))
        ->assertOk()
        ->assertSee('Edições');
});

test('listagem exibe as edicoes cadastradas', function () {
    edicaoComTermo([
        'id' => 101,
        'data' => '2026-10-01',
        'numero' => 1234,
        'paginas' => 20,
        'suplemento' => false,
        'tipo_edicao_nome' => 'Ordinária',
    ]);
    edicaoComTermo([
        'id' => 102,
        'data' => '2026-10-02',
        'numero' => null,
        'paginas' => null,
        'suplemento' => true,
        'tipo_edicao_nome' => null,
    ]);

    Livewire::test(Index::class)
        ->assertSee('01/10/2026')
        ->assertSee('Ordinária')
        ->assertSee('Sim');
});

test('busca filtra edicoes por data', function () {
    edicaoComTermo(['id' => 1, 'data' => '2026-09-01', 'suplemento' => false]);
    edicaoComTermo(['id' => 2, 'data' => '2026-10-15', 'suplemento' => false]);

    Livewire::test(Index::class)
        ->set('busca', '2026-10')
        ->assertSee('15/10/2026')
        ->assertDontSee('01/09/2026');
});

test('filtro por suplemento mostra apenas suplementos', function () {
    edicaoComTermo(['id' => 1, 'data' => '2026-10-01', 'suplemento' => false]);
    edicaoComTermo(['id' => 2, 'data' => '2026-10-02', 'suplemento' => true]);

    Livewire::test(Index::class)
        ->set('filtroSuplemento', 'sim')
        ->assertSee('02/10/2026')
        ->assertDontSee('01/10/2026');
});

test('intervalo de datas filtra edicoes', function () {
    edicaoComTermo(['id' => 1, 'data' => '2026-09-01', 'suplemento' => false]);
    edicaoComTermo(['id' => 2, 'data' => '2026-10-15', 'suplemento' => false]);

    Livewire::test(Index::class)
        ->set('inicio', '2026-10-01')
        ->set('fim', '2026-10-31')
        ->assertSee('15/10/2026')
        ->assertDontSee('01/09/2026');
});

test('busca aceita data no formato dd/mm/aaaa', function () {
    edicaoComTermo(['id' => 1, 'data' => '2026-09-01', 'suplemento' => false]);
    edicaoComTermo(['id' => 2, 'data' => '2026-10-15', 'suplemento' => false]);

    Livewire::test(Index::class)
        ->set('busca', '15/10/2026')
        ->assertSee('15/10/2026')
        ->assertDontSee('01/09/2026');
});

test('paginacao por cursor nao repete nem pula edicoes da mesma data', function () {
    foreach (range(1, 16) as $id) {
        edicaoComTermo(['id' => $id, 'data' => '2026-10-01', 'suplemento' => false]);
    }

    $listagem = Livewire::test(Index::class);
    $primeiraPagina = $listagem->instance()->edicoes;

    expect($primeiraPagina->pluck('id')->all())->toBe(range(16, 2));

    $listagem->call('setPage', $primeiraPagina->nextCursor()->encode(), 'cursor');

    expect($listagem->instance()->edicoes->pluck('id')->all())->toBe([1]);
});

test('alterar um filtro volta para o inicio da listagem', function () {
    foreach (range(1, 16) as $id) {
        edicaoComTermo(['id' => $id, 'data' => '2026-10-01', 'suplemento' => false]);
    }

    $listagem = Livewire::test(Index::class);

    $listagem->call('setPage', $listagem->instance()->edicoes->nextCursor()->encode(), 'cursor')
        ->set('busca', '2026-10');

    expect($listagem->instance()->edicoes->pluck('id')->first())->toBe(16);
});

test('so lista edicoes em que um termo ativo foi encontrado', function () {
    $ativo = Term::factory()->create(['termo' => 'Maiquinique']);
    $inativo = Term::factory()->inactive()->create(['termo' => 'Potiraguá']);

    edicaoComTermo(['id' => 1, 'data' => '2026-10-01', 'suplemento' => false], $ativo);
    edicaoComTermo(['id' => 2, 'data' => '2026-10-02', 'suplemento' => false], $inativo);
    Edition::query()->create(['id' => 3, 'data' => '2026-10-03', 'suplemento' => false]);

    Livewire::test(Index::class)
        ->assertSee('01/10/2026')
        ->assertSee('Maiquinique')
        ->assertDontSee('02/10/2026')
        ->assertDontSee('03/10/2026');
});
