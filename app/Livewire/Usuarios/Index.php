<?php

namespace App\Livewire\Usuarios;

use App\Exceptions\OperationNotAllowed;
use App\Exceptions\UserAlreadyExists;
use App\Models\PanelUser;
use App\Services\UserService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Usuários')]
class Index extends Component
{
    public bool $formAberto = false;

    public string $username = '';

    public string $password = '';

    public bool $senhaAberta = false;

    public ?int $senhaUserId = null;

    public string $senhaNova = '';

    public bool $exclusaoAberta = false;

    public ?int $excluindoId = null;

    public function mount(): void
    {
        Gate::authorize('manage-panel-accounts');
    }

    /**
     * Panel accounts ordered by id.
     *
     * @return Collection<int, PanelUser>
     */
    #[Computed]
    public function usuarios(): Collection
    {
        return PanelUser::query()->orderBy('id')->get();
    }

    public function criar(): void
    {
        $this->resetForm();
        $this->formAberto = true;
    }

    public function fecharForm(): void
    {
        $this->formAberto = false;
        $this->resetForm();
    }

    public function salvar(): void
    {
        $this->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ], [
            'username.regex' => 'username: letras, dígitos, ponto, hífen e sublinhado',
        ], [
            'username' => 'username',
            'password' => 'senha',
        ]);

        try {
            app(UserService::class)->createAdmin([
                'username' => $this->username,
                'password' => $this->password,
            ]);
        } catch (UserAlreadyExists) {
            $this->addError('username', 'Este username já está em uso.');

            return;
        }

        $this->fecharForm();

        Flux::toast(variant: 'success', text: 'Conta criada com sucesso.');
    }

    public function alternarAtivo(int $id): void
    {
        $alvo = PanelUser::query()->findOrFail($id);

        try {
            app(UserService::class)->update(null, $id, [
                'ativo' => ! $alvo->ativo,
            ]);
        } catch (OperationNotAllowed) {
            Flux::toast(variant: 'danger', text: 'Você não pode alterar esta conta.');

            return;
        }

        Flux::toast(variant: 'success', text: 'Conta atualizada com sucesso.');
    }

    public function abrirSenha(int $id): void
    {
        $this->senhaUserId = $id;
        $this->senhaNova = '';
        $this->senhaAberta = true;
    }

    public function fecharSenha(): void
    {
        $this->senhaAberta = false;
        $this->senhaUserId = null;
        $this->senhaNova = '';
    }

    public function salvarSenha(): void
    {
        $this->validate([
            'senhaNova' => ['required', 'string', 'min:8', 'max:128'],
        ], [], [
            'senhaNova' => 'nova senha',
        ]);

        try {
            app(UserService::class)->update(null, (int) $this->senhaUserId, [
                'password' => $this->senhaNova,
            ]);
        } catch (OperationNotAllowed) {
            Flux::toast(variant: 'danger', text: 'Você não pode alterar esta conta.');

            return;
        }

        $this->fecharSenha();

        Flux::toast(variant: 'success', text: 'Senha redefinida com sucesso.');
    }

    public function confirmarExclusao(int $id): void
    {
        $this->excluindoId = $id;
        $this->exclusaoAberta = true;
    }

    public function excluir(): void
    {
        try {
            app(UserService::class)->delete(null, (int) $this->excluindoId);
        } catch (OperationNotAllowed) {
            $this->exclusaoAberta = false;
            $this->excluindoId = null;

            Flux::toast(variant: 'danger', text: 'Você não pode excluir esta conta.');

            return;
        }

        $this->exclusaoAberta = false;
        $this->excluindoId = null;

        Flux::toast(variant: 'success', text: 'Conta excluída com sucesso.');
    }

    private function resetForm(): void
    {
        $this->username = '';
        $this->password = '';
    }

    public function render(): View
    {
        return view('livewire.usuarios');
    }
}
