<?php

namespace App\Livewire\Termos;

use App\Concerns\TermoValidationRules;
use App\Enums\TermType;
use App\Exceptions\TermAlreadyExists;
use App\Exceptions\TermNotFound;
use App\Models\Term;
use App\Services\TermService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Termos')]
class Index extends Component
{
    use TermoValidationRules;

    public string $busca = '';

    public string $filtroTipo = 'municipio';

    public bool $filtroAtivo = true;

    public bool $formAberto = false;

    public ?int $editandoId = null;

    public string $editandoTermo = '';

    public string $editandoTipo = 'municipio';

    public bool $editandoAtivo = true;

    public string $editandoVariacoes = '';

    public bool $exclusaoAberta = false;

    public ?int $excluindoId = null;

    /**
     * Terms honoring the search and the filters.
     *
     * @return Collection<int, Term>
     */
    public function termos(): Collection
    {
        return Term::listagem([
            'busca' => $this->busca,
            'tipo' => $this->filtroTipo,
            'apenas_ativos' => $this->filtroAtivo,
        ]);
    }

    public function criar(): void
    {
        $this->resetForm();
        $this->editandoId = null;
        $this->formAberto = true;
    }

    public function editar(int $id): void
    {
        $termo = Term::query()->findOrFail($id);

        $this->resetForm();
        $this->editandoId = $termo->id;
        $this->editandoTermo = $termo->termo;
        $this->editandoTipo = $termo->tipo->value;
        $this->editandoAtivo = (bool) $termo->ativo;
        $this->editandoVariacoes = implode("\n", $termo->listaVariacoes());
        $this->formAberto = true;
    }

    public function fecharForm(): void
    {
        $this->formAberto = false;
        $this->resetForm();
        $this->editandoId = null;
    }

    public function salvar(): void
    {
        $this->editandoVariacoes = implode("\n", array_filter(array_map('trim', explode("\n", $this->editandoVariacoes))));

        $validado = $this->validate([
            'editandoTermo' => ['required', 'string', 'max:120'],
            'editandoTipo' => ['required', 'in:'.implode(',', $this->tiposTermosDisponiveis())],
            'editandoAtivo' => ['required', 'boolean'],
        ], [
            'editandoTermo.required' => 'O campo termo é obrigatório.',
            'editandoTermo.max' => 'O campo termo não pode ter mais de 120 caracteres.',
            'editandoTipo.required' => 'O campo tipo é obrigatório.',
            'editandoTipo.in' => 'O tipo deve ser um dos valores permitidos.',
        ], [
            'editandoTermo' => 'termo',
            'editandoTipo' => 'tipo',
            'editandoAtivo' => 'ativo',
        ]);

        $variacoes = array_values(array_filter(array_map('trim', explode("\n", $this->editandoVariacoes))));
        $service = app(TermService::class);

        try {
            if ($this->editandoId !== null) {
                $service->update($this->editandoId, [
                    'termo' => $validado['editandoTermo'],
                    'tipo' => $validado['editandoTipo'],
                    'ativo' => $validado['editandoAtivo'],
                    'variacoes' => array_slice($variacoes, 0, 10),
                ]);

                $mensagem = 'Termo atualizado com sucesso.';
            } else {
                $service->create([
                    'termo' => $validado['editandoTermo'],
                    'tipo' => $validado['editandoTipo'],
                    'ativo' => $validado['editandoAtivo'],
                    'variacoes' => array_slice($variacoes, 0, 10),
                ]);

                $mensagem = 'Termo cadastrado com sucesso.';
            }
        } catch (TermAlreadyExists) {
            $this->addError('editandoTermo', 'Este termo já está cadastrado.');

            return;
        } catch (TermNotFound) {
            $this->addError('editandoTermo', 'Termo não encontrado.');

            return;
        }

        $this->fecharForm();

        Flux::toast(variant: 'success', text: $mensagem);
    }

    public function confirmarExclusao(int $id): void
    {
        $this->excluindoId = $id;
        $this->exclusaoAberta = true;
    }

    public function excluir(): void
    {
        app(TermService::class)->delete((int) $this->excluindoId);

        $this->exclusaoAberta = false;
        $this->excluindoId = null;

        Flux::toast(variant: 'success', text: 'Termo excluído com sucesso.');
    }

    private function resetForm(): void
    {
        $this->editandoTermo = '';
        $this->editandoTipo = TermType::Municipio->value;
        $this->editandoAtivo = true;
        $this->editandoVariacoes = '';
    }

    public function render(): View
    {
        return view('livewire.termos', [
            'termos' => $this->termos(),
        ]);
    }
}
