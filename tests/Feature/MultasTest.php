<?php

use App\Enums\MultaStatus;
use App\Enums\TermType;
use App\Livewire\Multas\Index;
use App\Models\Multa;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('visitantes sao redirecionados para o login', function () {
    $this->get(route('multas.index'))->assertRedirect(route('login'));
});

test('usuario autenticado abre a listagem de multas', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('multas.index'))
        ->assertOk()
        ->assertSee('Nova multa');
});

test('cadastro cria multa vinculada ao termo', function () {
    $termo = Term::factory()->create();

    Livewire::test(Index::class)
        ->call('criar')
        ->set('termoId', (string) $termo->id)
        ->set('gestor', 'Joao da Silva')
        ->set('processoNumero', '12345')
        ->set('dataMulta', '2026-09-01')
        ->set('dataVencimento', '2026-10-01')
        ->set('valor', '1500.75')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('formAberto', false);

    $multa = Multa::query()->sole();

    expect($multa->termo_id)->toBe($termo->id)
        ->and($multa->gestor)->toBe('Joao da Silva')
        ->and($multa->processo_numero)->toBe('12345')
        ->and($multa->data_multa->format('Y-m-d'))->toBe('2026-09-01')
        ->and($multa->data_vencimento->format('Y-m-d'))->toBe('2026-10-01')
        ->and((float) $multa->valor)->toBe(1500.75)
        ->and($multa->situacao)->toBe(MultaStatus::Pendente);
});

test('cadastro exige os campos obrigatorios', function () {
    Livewire::test(Index::class)
        ->call('criar')
        ->call('salvar')
        ->assertHasErrors([
            'termoId',
            'gestor',
            'processoNumero',
            'dataMulta',
            'dataVencimento',
            'valor',
        ]);

    expect(Multa::query()->count())->toBe(0);
});

test('vencimento anterior a data da multa e recusado', function () {
    $termo = Term::factory()->create();

    Livewire::test(Index::class)
        ->call('criar')
        ->set('termoId', (string) $termo->id)
        ->set('gestor', 'Maria Souza')
        ->set('processoNumero', '99887')
        ->set('dataMulta', '2026-10-10')
        ->set('dataVencimento', '2026-10-01')
        ->set('valor', '250')
        ->call('salvar')
        ->assertHasErrors(['dataVencimento']);

    expect(Multa::query()->count())->toBe(0);
});

test('nao e possivel vincular multa a termo que nao e municipio', function () {
    $empresa = Term::factory()->create(['tipo' => TermType::Empresa]);

    Livewire::test(Index::class)
        ->call('criar')
        ->set('termoId', (string) $empresa->id)
        ->set('gestor', 'Maria Souza')
        ->set('processoNumero', '11223')
        ->set('dataMulta', '2026-09-01')
        ->set('dataVencimento', '2026-10-01')
        ->set('valor', '300')
        ->call('salvar')
        ->assertHasErrors(['termoId']);

    expect(Multa::query()->count())->toBe(0);
});

test('comprovante e anexado quando a multa esta paga', function () {
    Storage::fake('local');
    $termo = Term::factory()->create();

    Livewire::test(Index::class)
        ->call('criar')
        ->set('termoId', (string) $termo->id)
        ->set('gestor', 'Carlos Lima')
        ->set('processoNumero', '55443')
        ->set('dataMulta', '2026-09-01')
        ->set('dataVencimento', '2026-10-01')
        ->set('valor', '900')
        ->set('situacao', 'paga')
        ->set('comprovante', UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf'))
        ->call('salvar')
        ->assertHasNoErrors();

    $multa = Multa::query()->sole();

    expect($multa->situacao)->toBe(MultaStatus::Paga)
        ->and($multa->comprovante)->not->toBeNull();

    Storage::disk('local')->assertExists($multa->comprovante);
});

test('edicao atualiza a multa sem apagar o comprovante existente', function () {
    Storage::fake('local');
    Storage::disk('local')->put('multas/existente.pdf', 'conteudo');

    $multa = Multa::factory()->create([
        'situacao' => MultaStatus::Paga,
        'comprovante' => 'multas/existente.pdf',
        'gestor' => 'Gestor Original',
    ]);

    Livewire::test(Index::class)
        ->call('editar', $multa->id)
        ->set('gestor', 'Gestor Atualizado')
        ->call('salvar')
        ->assertHasNoErrors();

    $multa->refresh();

    expect($multa->gestor)->toBe('Gestor Atualizado')
        ->and($multa->comprovante)->toBe('multas/existente.pdf');

    Storage::disk('local')->assertExists('multas/existente.pdf');
});

test('novo comprovante substitui o anterior', function () {
    Storage::fake('local');
    Storage::disk('local')->put('multas/anterior.pdf', 'conteudo antigo');

    $multa = Multa::factory()->create([
        'situacao' => MultaStatus::Paga,
        'comprovante' => 'multas/anterior.pdf',
    ]);

    Livewire::test(Index::class)
        ->call('editar', $multa->id)
        ->set('comprovante', UploadedFile::fake()->create('novo.pdf', 50, 'application/pdf'))
        ->call('salvar')
        ->assertHasNoErrors();

    $multa->refresh();

    expect($multa->comprovante)->not->toBe('multas/anterior.pdf');

    Storage::disk('local')->assertMissing('multas/anterior.pdf');
    Storage::disk('local')->assertExists($multa->comprovante);
});

test('busca filtra multas por processo, gestor ou municipio', function () {
    $termo = Term::factory()->create(['termo' => 'Municipio Busca']);

    Multa::factory()->create([
        'termo_id' => $termo->id,
        'gestor' => 'Gestor Alfa',
        'processo_numero' => 'AAA111',
    ]);
    Multa::factory()->create([
        'gestor' => 'Gestor Beta',
        'processo_numero' => 'BBB222',
    ]);

    Livewire::test(Index::class)
        ->set('busca', 'BBB222')
        ->assertSee('Gestor Beta')
        ->assertDontSee('Gestor Alfa');
});

test('listagem filtra multas pelo termo informado na url', function () {
    $this->actingAs(User::factory()->create());

    $termoA = Term::factory()->create();
    $termoB = Term::factory()->create();

    Multa::factory()->create(['termo_id' => $termoA->id, 'gestor' => 'Gestor Do Municipio Um']);
    Multa::factory()->create(['termo_id' => $termoB->id, 'gestor' => 'Gestor Do Municipio Dois']);

    $this->get(route('multas.index', ['termo' => $termoA->id]))
        ->assertOk()
        ->assertSee('Gestor Do Municipio Um')
        ->assertDontSee('Gestor Do Municipio Dois');
});

test('exclusao remove a multa e o comprovante', function () {
    Storage::fake('local');
    Storage::disk('local')->put('multas/para-excluir.pdf', 'conteudo');

    $multa = Multa::factory()->create(['comprovante' => 'multas/para-excluir.pdf']);

    Livewire::test(Index::class)
        ->call('confirmarExclusao', $multa->id)
        ->assertSet('exclusaoAberta', true)
        ->call('excluir')
        ->assertSet('exclusaoAberta', false);

    $this->assertModelMissing($multa);

    Storage::disk('local')->assertMissing('multas/para-excluir.pdf');
});

test('visitante nao baixa o comprovante', function () {
    $this->get(route('multas.comprovante', 1))->assertRedirect(route('login'));
});

test('rota do comprovante baixa o arquivo anexado', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());

    Storage::disk('local')->put('multas/comprovante.pdf', 'pdf de teste');

    $multa = Multa::factory()->create(['comprovante' => 'multas/comprovante.pdf']);

    $this->get(route('multas.comprovante', $multa))
        ->assertOk()
        ->assertDownload();
});

test('rota do comprovante retorna 404 sem anexo', function () {
    $this->actingAs(User::factory()->create());

    $multa = Multa::factory()->create();

    $this->get(route('multas.comprovante', $multa))->assertNotFound();
});

test('multa pendente apos o vencimento e sinalizada como vencida', function () {
    Multa::factory()->create(['data_vencimento' => now()->subDay()->format('Y-m-d')]);

    Livewire::test(Index::class)->assertSee('Vencida');
});

test('multa paga ou ainda no prazo nao e sinalizada como vencida', function () {
    Multa::factory()->paga()->create(['data_vencimento' => now()->subDay()->format('Y-m-d')]);
    Multa::factory()->create(['data_vencimento' => now()->addDay()->format('Y-m-d')]);

    Livewire::test(Index::class)->assertDontSee('Vencida');
});

test('paginacao por cursor nao repete nem pula multas da mesma data', function () {
    $multas = Multa::factory()->count(11)->create(['data_multa' => '2026-09-01', 'data_vencimento' => '2026-10-01']);
    $idsEmOrdem = $multas->pluck('id')->sortDesc()->values()->all();

    $listagem = Livewire::test(Index::class);
    $primeiraPagina = $listagem->instance()->multas;

    $listagem->call('setPage', $primeiraPagina->nextCursor()->encode(), 'cursor');

    expect([...$primeiraPagina->pluck('id')->all(), ...$listagem->instance()->multas->pluck('id')->all()])
        ->toBe($idsEmOrdem);
});
