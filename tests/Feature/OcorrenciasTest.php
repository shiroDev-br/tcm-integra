<?php

use App\Enums\SearchStatus;
use App\Enums\TermType;
use App\Livewire\Ocorrencias\Index;
use App\Models\Edition;
use App\Models\Occurrence;
use App\Models\Process;
use App\Models\SearchRun;
use App\Models\Term;
use App\Models\TermProcess;
use App\Models\User;
use App\Services\Clock;
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

test('cards mostram ocorrencias e processos de cada cadastro', function () {
    $termo = Term::factory()->create(['termo' => 'Itarantim']);
    $edicao = Edition::query()->create(['id' => 70, 'data' => '2026-11-03', 'suplemento' => false]);

    Occurrence::query()->create([
        'edicao_id' => $edicao->id,
        'pagina' => 4,
        'termo_id' => $termo->id,
        'trecho' => 'Itarantim',
        'contexto' => 'ctx',
        'atos' => [],
        'processos' => ['PROC70'],
        'tipo' => 'mencao',
        'ocorrencias_na_pagina' => 1,
        'pdf_url' => '',
        'periodo_inicio' => '2026-11-03',
        'periodo_fim' => '2026-11-03',
        'created_at' => '2026-11-03T12:00:00Z',
    ]);
    TermProcess::query()->insert([
        ['termo_id' => $termo->id, 'processo_numero' => 'PROC70', 'periodo_inicio' => '2026-11-03', 'periodo_fim' => '2026-11-03', 'created_at' => '2026-11-03T12:00:00Z'],
        ['termo_id' => $termo->id, 'processo_numero' => 'PROC71', 'periodo_inicio' => '2026-11-03', 'periodo_fim' => '2026-11-03', 'created_at' => '2026-11-03T12:00:00Z'],
    ]);

    Livewire::test(Index::class)
        ->assertSee('Itarantim')
        ->assertSee('ocorrência encontrada')
        ->assertSee('2 processos citados');
});

test('linha da ocorrencia lista os processos citados e abre o detalhe', function () {
    $termo = Term::factory()->create();
    $edicao = Edition::query()->create(['id' => 71, 'data' => '2026-11-04', 'suplemento' => false]);

    Occurrence::query()->create([
        'edicao_id' => $edicao->id,
        'pagina' => 2,
        'termo_id' => $termo->id,
        'trecho' => 'Ato com processo',
        'contexto' => 'ctx',
        'atos' => [],
        'processos' => ['09768e26'],
        'tipo' => 'ato',
        'ocorrencias_na_pagina' => 1,
        'pdf_url' => '',
        'periodo_inicio' => '2026-11-04',
        'periodo_fim' => '2026-11-04',
        'created_at' => '2026-11-04T12:00:00Z',
    ]);

    Livewire::test(Index::class)
        ->call('abrirTermo', $termo->id)
        ->assertSee('09768e26')
        ->assertSeeHtml("abrir-processo', { numero: '09768e26' }");
});

test('trocar de aba mostra os processos do cadastro', function () {
    $termo = Term::factory()->create();
    Process::query()->create(['numero' => 'ABA001', 'encontrado' => true, 'autor' => 'Autor da aba', 'andamento' => []]);
    TermProcess::query()->insert([
        'termo_id' => $termo->id,
        'processo_numero' => 'ABA001',
        'periodo_inicio' => '2026-11-01',
        'periodo_fim' => '2026-11-01',
        'created_at' => '2026-11-01T12:00:00Z',
    ]);

    Livewire::test(Index::class)
        ->call('abrirTermo', $termo->id)
        ->assertDontSee('Autor da aba')
        ->set('aba', Index::ABA_PROCESSOS)
        ->assertSee('Autor da aba')
        ->set('aba', 'qualquer-coisa')
        ->assertSet('aba', Index::ABA_OCORRENCIAS);
});

/**
 * Today's collection that found the given terms.
 *
 * @param  list<Term>  $termos
 */
function coletaDeHojeEncontrou(array $termos): SearchRun
{
    return SearchRun::query()->create([
        'periodo_inicio' => Clock::today(),
        'periodo_fim' => Clock::today(),
        'termos' => count($termos),
        'status' => SearchStatus::Ok,
        'termos_encontrados' => array_map(
            fn (Term $termo): array => ['termo_id' => $termo->id, 'termo' => $termo->termo, 'ocorrencias' => 1, 'processos' => 0],
            $termos,
        ),
        'created_at' => Clock::nowUtcIso(),
    ]);
}

test('encontrados hoje ficam em destaque ate o usuario abrir', function () {
    $this->actingAs(User::factory()->create());
    $novo = Term::factory()->create(['termo' => 'Potiraguá']);
    Term::factory()->create(['termo' => 'Boa Nova']);
    coletaDeHojeEncontrou([$novo]);

    $pagina = Livewire::test(Index::class)
        ->assertSee('Encontrados hoje!')
        ->assertSeeInOrder(['Encontrados hoje!', 'Potiraguá', 'Boa Nova']);

    expect($pagina->instance()->encontradosHoje)->toBe([$novo->id]);

    $pagina->call('abrirTermo', $novo->id)
        ->call('voltarCards')
        ->assertDontSee('Encontrados hoje!')
        ->assertSee('Potiraguá');
});

test('cada usuario ve os encontrados de hoje ate abrir por conta propria', function () {
    $quemAbriu = User::factory()->create();
    $outro = User::factory()->create();
    $termo = Term::factory()->create();
    coletaDeHojeEncontrou([$termo]);

    $quemAbriu->marcarTermoComoVisto($termo->id);

    $this->actingAs($quemAbriu);
    Livewire::test(Index::class)->assertDontSee('Encontrados hoje!');

    $this->actingAs($outro);
    Livewire::test(Index::class)->assertSee('Encontrados hoje!');
});

test('abrir pelo link do dashboard tambem conta como aberto', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $termo = Term::factory()->create();
    coletaDeHojeEncontrou([$termo]);

    Livewire::withQueryParams(['termo' => $termo->id])->test(Index::class);

    expect(SearchRun::daColetaDeHoje()->termosNaoVistosPor($user))->toBe([]);
});

test('nova coleta que encontra o cadastro de novo volta a destacar', function () {
    $user = User::factory()->create();
    $termo = Term::factory()->create();
    $user->termosVistos()->attach($termo->id, ['visto_em' => '2026-01-01T00:00:00+00:00']);
    $coleta = coletaDeHojeEncontrou([$termo]);

    expect($coleta->termosNaoVistosPor($user))->toBe([$termo->id]);
});
