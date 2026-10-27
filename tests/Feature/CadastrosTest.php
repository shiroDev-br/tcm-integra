<?php

use App\Enums\TermType;
use App\Livewire\Cadastros\Index;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

test('visitantes sao redirecionados para o login', function () {
    $this->get(route('cadastros.index'))->assertRedirect(route('login'));
});

test('usuario autenticado abre a listagem de cadastros', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('cadastros.index'))
        ->assertOk()
        ->assertSee('Novo cadastro');
});

test('cadastro cria um termo', function () {
    Livewire::test(Index::class)
        ->call('criar')
        ->set('editandoTermo', 'Maiquinique')
        ->set('editandoTipo', 'municipio')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('formAberto', false);

    $termo = Term::query()->where('termo', 'Maiquinique')->sole();

    expect($termo->tipo->value)->toBe('municipio')
        ->and($termo->ativo)->toBeTrue();
});

test('cadastro exige o termo', function () {
    Livewire::test(Index::class)
        ->call('criar')
        ->call('salvar')
        ->assertHasErrors(['editandoTermo']);

    expect(Term::query()->count())->toBe(0);
});

test('cadastro recusa termo duplicado', function () {
    Term::factory()->create(['termo' => 'Duplicado']);

    Livewire::test(Index::class)
        ->call('criar')
        ->set('editandoTermo', 'Duplicado')
        ->call('salvar')
        ->assertHasErrors(['editandoTermo']);
});

test('edicao atualiza o termo e as variacoes', function () {
    $termo = Term::factory()->create(['termo' => 'Original', 'variacoes' => []]);

    Livewire::test(Index::class)
        ->call('editar', $termo->id)
        ->set('editandoTermo', 'Atualizado')
        ->set('editandoVariacoes', "Variacao Um\nVariacao Dois")
        ->call('salvar')
        ->assertHasNoErrors();

    $termo->refresh();

    expect($termo->termo)->toBe('Atualizado')
        ->and($termo->variacoes)->toBe(['Variacao Um', 'Variacao Dois']);
});

test('listagem filtra por busca e tipo', function () {
    Term::factory()->create(['termo' => 'Maiquinique']);
    Term::factory()->create(['termo' => 'Plenary', 'tipo' => TermType::Empresa]);

    Livewire::test(Index::class)
        ->set('busca', 'Maiqui')
        ->assertSee('Maiquinique')
        ->assertDontSee('Plenary');
});

test('exclusao remove o termo', function () {
    $termo = Term::factory()->create();

    Livewire::test(Index::class)
        ->call('confirmarExclusao', $termo->id)
        ->assertSet('exclusaoAberta', true)
        ->call('excluir')
        ->assertSet('exclusaoAberta', false);

    $this->assertModelMissing($termo);
});
