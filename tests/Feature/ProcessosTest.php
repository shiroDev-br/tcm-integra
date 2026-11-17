<?php

use App\Enums\ContagemDePrazo;
use App\Enums\TermType;
use App\Exceptions\SourceUnavailable;
use App\Livewire\Ocorrencias\Index;
use App\Livewire\Processos\Detalhe;
use App\Models\Edition;
use App\Models\Prazo;
use App\Models\Process;
use App\Models\Term;
use App\Models\TermProcess;
use App\Models\User;
use App\Services\Clock;
use App\Services\TcmProcessClient;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('link antigo de processos leva para a aba de processos', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/processos')->assertRedirect('/ocorrencias?aba=processos');
});

test('usuario autenticado abre a aba de processos', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('ocorrencias.index', ['aba' => 'processos']))
        ->assertOk()
        ->assertSee('Ocorrências e processos');
});

test('sem termo selecionado mostra cards dos termos ativos', function () {
    Term::factory()->create(['termo' => 'Maiquinique']);
    Term::factory()->create(['termo' => 'Plenary', 'tipo' => TermType::Empresa]);

    Livewire::test(Index::class)
        ->assertSee('Maiquinique')
        ->assertSee('Plenary')
        ->assertDontSee('Número');
});

test('clicar no card abre a listagem do termo', function () {
    $termo = Term::factory()->create(['termo' => 'Maiquinique']);
    Process::query()->create(['numero' => 'PROC01', 'encontrado' => true, 'andamento' => []]);
    TermProcess::query()->insert([
        'termo_id' => $termo->id,
        'processo_numero' => 'PROC01',
        'periodo_inicio' => '2026-10-01',
        'periodo_fim' => '2026-10-07',
        'created_at' => '2026-10-07T12:00:00Z',
    ]);

    Livewire::test(Index::class)
        ->set('aba', Index::ABA_PROCESSOS)
        ->call('abrirTermo', $termo->id)
        ->assertSet('filtroTermo', (string) $termo->id)
        ->assertSee('PROC01')
        ->assertSee('Voltar');
});

test('voltar para os cards limpa o filtro', function () {
    $termo = Term::factory()->create();

    Livewire::test(Index::class)
        ->set('aba', Index::ABA_PROCESSOS)
        ->call('abrirTermo', $termo->id)
        ->assertSet('filtroTermo', (string) $termo->id)
        ->call('voltarCards')
        ->assertSet('filtroTermo', '')
        ->assertSee($termo->termo);
});

test('listagem exibe os processos com situacao', function () {
    $termo = Term::factory()->create();

    Process::query()->create([
        'numero' => '09768e26',
        'encontrado' => true,
        'data_entrada' => '2024-01-15',
        'autor' => 'Prefeitura Municipal',
        'situacao' => 'Em análise',
        'situacao_data' => '2025-06-01',
        'andamento' => [],
    ]);
    Process::query()->create([
        'numero' => '11111e11',
        'encontrado' => false,
        'andamento' => [],
    ]);

    TermProcess::query()->insert([
        ['termo_id' => $termo->id, 'processo_numero' => '09768e26', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-07', 'created_at' => '2026-10-07T12:00:00Z'],
        ['termo_id' => $termo->id, 'processo_numero' => '11111e11', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-07', 'created_at' => '2026-10-07T12:00:00Z'],
    ]);

    Livewire::test(Index::class)
        ->set('aba', Index::ABA_PROCESSOS)
        ->call('abrirTermo', $termo->id)
        ->assertSee('09768e26')
        ->assertSee('Prefeitura Municipal')
        ->assertSee('Localizado')
        ->assertSee('11111e11')
        ->assertSee('Não localizado');
});

test('busca filtra processos por numero ou autor', function () {
    $termo = Term::factory()->create();

    Process::query()->create(['numero' => 'AAAA11', 'encontrado' => true, 'autor' => 'Joao', 'andamento' => []]);
    Process::query()->create(['numero' => 'BBBB22', 'encontrado' => true, 'autor' => 'Maria', 'andamento' => []]);

    TermProcess::query()->insert([
        ['termo_id' => $termo->id, 'processo_numero' => 'AAAA11', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-07', 'created_at' => '2026-10-07T12:00:00Z'],
        ['termo_id' => $termo->id, 'processo_numero' => 'BBBB22', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-07', 'created_at' => '2026-10-07T12:00:00Z'],
    ]);

    Livewire::test(Index::class)
        ->set('aba', Index::ABA_PROCESSOS)
        ->call('abrirTermo', $termo->id)
        ->set('busca', 'BBBB22')
        ->assertSee('BBBB22')
        ->assertDontSee('AAAA11');
});

test('filtro por situacao mostra apenas processos localizados', function () {
    $termo = Term::factory()->create();

    Process::query()->create(['numero' => 'LOC111', 'encontrado' => true, 'andamento' => []]);
    Process::query()->create(['numero' => 'NLOC22', 'encontrado' => false, 'andamento' => []]);

    TermProcess::query()->insert([
        ['termo_id' => $termo->id, 'processo_numero' => 'LOC111', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-07', 'created_at' => '2026-10-07T12:00:00Z'],
        ['termo_id' => $termo->id, 'processo_numero' => 'NLOC22', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-07', 'created_at' => '2026-10-07T12:00:00Z'],
    ]);

    Livewire::test(Index::class)
        ->set('aba', Index::ABA_PROCESSOS)
        ->call('abrirTermo', $termo->id)
        ->set('filtroSituacao', 'localizado')
        ->assertSee('LOC111')
        ->assertDontSee('NLOC22');
});

test('detalhe exibe andamento e termos vinculados', function () {
    $termo = Term::factory()->create(['termo' => 'Maiquinique']);

    Process::query()->create([
        'numero' => '09768e26',
        'encontrado' => true,
        'autor' => 'Prefeitura de Maiquinique',
        'andamento' => [
            ['unidade' => 'Secretaria', 'data' => '2025-01-01', 'situacao' => 'Diligência', 'observacao' => 'ok'],
        ],
    ]);

    TermProcess::query()->insert([
        'termo_id' => $termo->id,
        'processo_numero' => '09768e26',
        'periodo_inicio' => '2026-10-01',
        'periodo_fim' => '2026-10-07',
        'created_at' => '2026-10-07T12:00:00Z',
    ]);

    Livewire::test(Detalhe::class)
        ->call('abrir', '09768e26')
        ->assertSet('aberto', true)
        ->assertSee('Prefeitura de Maiquinique')
        ->assertSee('Secretaria')
        ->assertSee('Maiquinique');
});

/**
 * Process cited by the term with a deadline due N days from today (null = no deadline).
 */
function processoComPrazo(Term $termo, string $numero, ?int $diasAteVencer): void
{
    Process::query()->create(['numero' => $numero, 'encontrado' => true, 'andamento' => []]);
    TermProcess::query()->insert([
        'termo_id' => $termo->id,
        'processo_numero' => $numero,
        'periodo_inicio' => '2026-10-01',
        'periodo_fim' => '2026-10-07',
        'created_at' => '2026-10-07T12:00:00Z',
    ]);

    if ($diasAteVencer === null) {
        return;
    }

    $hoje = CarbonImmutable::parse(Clock::today());
    Edition::query()->firstOrCreate(['id' => 22737], ['data' => '2026-10-23', 'suplemento' => false]);
    Prazo::query()->create([
        'processo_numero' => $numero,
        'edicao_id' => 22737,
        'pagina' => 21,
        'dias' => 15,
        'contagem' => ContagemDePrazo::Corridos,
        'data_inicio' => $hoje->subDays(15)->format('Y-m-d'),
        'data_vencimento' => $hoje->addDays($diasAteVencer)->format('Y-m-d'),
        'estimado' => false,
        'trecho' => "no prazo de 15 (quinze) dias - {$numero}",
        'created_at' => Clock::nowUtcIso(),
    ]);
    Process::atualizarPrazoVencimento([$numero]);
}

test('listagem mostra primeiro o prazo mais curto, depois vencidos e sem prazo', function () {
    $termo = Term::factory()->create();

    processoComPrazo($termo, 'AAAA01', null);
    processoComPrazo($termo, 'BBBB02', -5);
    processoComPrazo($termo, 'CCCC03', 20);
    processoComPrazo($termo, 'DDDD04', 2);

    Livewire::test(Index::class)
        ->set('aba', Index::ABA_PROCESSOS)
        ->call('abrirTermo', $termo->id)
        ->assertSeeInOrder(['DDDD04', 'Vence em 2 dias', 'CCCC03', 'Vence em 20 dias', 'BBBB02', 'Vencido', 'AAAA01']);
});

test('cursor percorre os grupos de prazo sem perder processos', function () {
    $termo = Term::factory()->create();

    processoComPrazo($termo, 'AAAA01', null);
    processoComPrazo($termo, 'BBBB02', null);
    processoComPrazo($termo, 'CCCC03', -1);
    processoComPrazo($termo, 'DDDD04', 0);
    processoComPrazo($termo, 'EEEE05', 3);

    $numeros = [];
    $cursor = null;

    do {
        $pagina = Process::query()->porPrazoMaisCurto()->cursorPaginate(2, ['*'], 'cursor', $cursor);
        $numeros = [...$numeros, ...$pagina->pluck('numero')->all()];
        $cursor = $pagina->nextCursor();
    } while ($cursor !== null);

    expect($numeros)->toBe(['DDDD04', 'EEEE05', 'CCCC03', 'AAAA01', 'BBBB02']);
});

test('detalhe exibe os prazos publicados', function () {
    $termo = Term::factory()->create();
    processoComPrazo($termo, 'PRZ001', 4);

    Livewire::test(Detalhe::class)
        ->call('abrir', 'PRZ001')
        ->assertSee('Prazos publicados')
        ->assertSee('Vence em 4 dias')
        ->assertSee('15 dias corridos')
        ->assertSee('Diário Oficial, página 21');
});

test('editar o prazo ajusta o vencimento e a listagem', function () {
    $termo = Term::factory()->create();
    processoComPrazo($termo, 'PRZ002', 4);
    $prazo = Prazo::query()->where('processo_numero', 'PRZ002')->sole();
    $novoVencimento = CarbonImmutable::parse(Clock::today())->addDays(10)->format('Y-m-d');

    Livewire::test(Detalhe::class)
        ->call('abrir', 'PRZ002')
        ->call('editarPrazo', $prazo->id)
        ->assertSet('novoVencimento', $prazo->data_vencimento)
        ->set('novoVencimento', $novoVencimento)
        ->call('salvarPrazo')
        ->assertHasNoErrors()
        ->assertSet('prazoEmEdicao', null)
        ->assertDispatched('processo-atualizado', numero: 'PRZ002')
        ->assertSee('Vence em 10 dias')
        ->assertSee('Vencimento ajustado manualmente');

    expect($prazo->fresh()->data_vencimento)->toBe($novoVencimento)
        ->and($prazo->fresh()->ajustado)->toBeTrue()
        ->and(Process::query()->find('PRZ002')->prazo_vencimento)->toBe($novoVencimento);
});

test('editar o prazo exige uma data valida', function () {
    $termo = Term::factory()->create();
    processoComPrazo($termo, 'PRZ003', 4);
    $prazo = Prazo::query()->where('processo_numero', 'PRZ003')->sole();

    Livewire::test(Detalhe::class)
        ->call('abrir', 'PRZ003')
        ->call('editarPrazo', $prazo->id)
        ->set('novoVencimento', '31/12/2026')
        ->call('salvarPrazo')
        ->assertHasErrors('novoVencimento');

    expect($prazo->fresh()->ajustado)->toBeFalse();
});

test('nao edita prazo de outro processo', function () {
    $termo = Term::factory()->create();
    processoComPrazo($termo, 'PRZ004', 4);
    processoComPrazo($termo, 'PRZ005', 6);
    $prazoDeOutro = Prazo::query()->where('processo_numero', 'PRZ005')->sole();

    Livewire::test(Detalhe::class)
        ->call('abrir', 'PRZ004')
        ->call('editarPrazo', $prazoDeOutro->id)
        ->assertNotFound();
});

test('prazo ajustado nao vira o atual no lugar do ato mais recente', function () {
    $termo = Term::factory()->create();
    processoComPrazo($termo, 'PRZ006', 4);
    $hoje = CarbonImmutable::parse(Clock::today());
    Edition::query()->create(['id' => 22800, 'data' => '2026-10-30', 'suplemento' => false]);
    $recente = Prazo::query()->create([
        'processo_numero' => 'PRZ006',
        'edicao_id' => 22800,
        'pagina' => 3,
        'dias' => 10,
        'contagem' => ContagemDePrazo::Corridos,
        'data_inicio' => $hoje->subDays(2)->format('Y-m-d'),
        'data_vencimento' => $hoje->addDays(8)->format('Y-m-d'),
        'estimado' => false,
        'trecho' => 'no prazo de 10 (dez) dias',
        'created_at' => Clock::nowUtcIso(),
    ]);

    $recente->ajustarVencimento($hoje->addDays(2)->format('Y-m-d'));

    expect(Process::query()->find('PRZ006')->prazo_vencimento)->toBe($hoje->addDays(2)->format('Y-m-d'))
        ->and(Process::query()->with('prazoAtual')->find('PRZ006')->prazoAtual->id)->toBe($recente->id);
});

test('buscar andamento novamente atualiza o processo com o portal', function () {
    Process::query()->create(['numero' => 'AND001', 'encontrado' => true, 'situacao' => 'Antiga', 'andamento' => []]);

    $this->mock(TcmProcessClient::class)
        ->shouldReceive('lookup')
        ->with('AND001')
        ->once()
        ->andReturn([
            'numero' => 'AND001',
            'encontrado' => true,
            'data_entrada' => '2025-01-10',
            'autor' => 'Prefeitura Municipal',
            'complemento' => null,
            'municipio' => 'Maiquinique',
            'situacao' => 'Em julgamento',
            'situacao_data' => '2026-11-09',
            'meio' => 'Eletrônico',
            'andamento' => [['unidade' => 'Plenário', 'data' => '2026-11-09', 'situacao' => 'Pautado', 'observacao' => '']],
            'consultado_em' => '2026-11-10T10:00:00-03:00',
        ]);

    Livewire::test(Detalhe::class)
        ->call('abrir', 'AND001')
        ->call('buscarAndamento')
        ->assertDispatched('processo-atualizado', numero: 'AND001')
        ->assertSee('Em julgamento')
        ->assertSee('Plenário');

    expect(Process::query()->find('AND001')->situacao)->toBe('Em julgamento');
});

test('falha no portal ao buscar andamento mantem os dados', function () {
    Process::query()->create(['numero' => 'AND002', 'encontrado' => true, 'situacao' => 'Antiga', 'andamento' => []]);

    $this->mock(TcmProcessClient::class)
        ->shouldReceive('lookup')
        ->andThrow(new SourceUnavailable('timeout'));

    Livewire::test(Detalhe::class)
        ->call('abrir', 'AND002')
        ->call('buscarAndamento')
        ->assertNotDispatched('processo-atualizado');

    expect(Process::query()->find('AND002')->situacao)->toBe('Antiga');
});
