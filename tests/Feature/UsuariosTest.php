<?php

use App\Livewire\Usuarios\Index;
use App\Models\PanelUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('visitantes sao redirecionados para o login', function () {
    $this->get(route('usuarios.index'))->assertRedirect(route('login'));
});

test('admin comum nao acessa a gestao de contas', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('usuarios.index'))->assertForbidden();
});

test('superadmin abre a listagem de contas', function () {
    $this->actingAs(User::factory()->superadmin()->create());

    $this->get(route('usuarios.index'))
        ->assertOk()
        ->assertSee('Nova conta');
});

test('listagem exibe as contas do painel', function () {
    PanelUser::factory()->create(['username' => 'admin01']);
    PanelUser::factory()->superAdmin()->create(['username' => 'chefe']);

    $this->actingAs(User::factory()->superadmin()->create());

    $this->get(route('usuarios.index'))
        ->assertOk()
        ->assertSee('admin01')
        ->assertSee('chefe');
});

test('criacao de conta sempre e admin', function () {
    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(Index::class)
        ->call('criar')
        ->set('username', 'novo.admin')
        ->set('password', 'senha-segura-123')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('formAberto', false);

    $conta = PanelUser::query()->where('username', 'novo.admin')->sole();

    expect($conta->role->value)->toBe('admin')
        ->and($conta->ativo)->toBeTrue();
});

test('criacao valida campos obrigatorios', function () {
    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(Index::class)
        ->call('criar')
        ->call('salvar')
        ->assertHasErrors(['username', 'password']);

    expect(PanelUser::query()->count())->toBe(0);
});

test('criacao recusa username duplicado', function () {
    PanelUser::factory()->create(['username' => 'duplicado']);

    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(Index::class)
        ->call('criar')
        ->set('username', 'duplicado')
        ->set('password', 'senha-segura-123')
        ->call('salvar')
        ->assertHasErrors(['username']);

    expect(PanelUser::query()->count())->toBe(1);
});

test('alternar ativo desativa e reativa a conta', function () {
    $conta = PanelUser::factory()->create(['ativo' => true]);

    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(Index::class)
        ->call('alternarAtivo', $conta->id);

    expect($conta->refresh()->ativo)->toBeFalse();

    Livewire::test(Index::class)
        ->call('alternarAtivo', $conta->id);

    expect($conta->refresh()->ativo)->toBeTrue();
});

test('redefinir senha atualiza o hash', function () {
    $conta = PanelUser::factory()->create();

    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(Index::class)
        ->call('abrirSenha', $conta->id)
        ->set('senhaNova', 'nova-senha-999')
        ->call('salvarSenha')
        ->assertHasNoErrors()
        ->assertSet('senhaAberta', false);

    expect(Hash::check('nova-senha-999', $conta->refresh()->senha))->toBeTrue();
});

test('exclusao remove a conta', function () {
    $conta = PanelUser::factory()->create();

    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(Index::class)
        ->call('confirmarExclusao', $conta->id)
        ->assertSet('exclusaoAberta', true)
        ->call('excluir')
        ->assertSet('exclusaoAberta', false);

    $this->assertModelMissing($conta);
});

test('superadmin da api nao pode ser excluido', function () {
    $conta = PanelUser::factory()->superAdmin()->create();

    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(Index::class)
        ->call('confirmarExclusao', $conta->id)
        ->call('excluir');

    $this->assertModelExists($conta);
});
