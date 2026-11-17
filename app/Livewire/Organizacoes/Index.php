<?php

namespace App\Livewire\Organizacoes;

use App\Models\Organizacao;
use Flux\Flux;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Organizador de prestação de contas')]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $formAberto = false;

    public string $entidade = '';

    /** @var list<TemporaryUploadedFile> */
    public array $arquivos = [];

    public bool $detalheAberto = false;

    public ?int $detalheId = null;

    public bool $exclusaoAberta = false;

    public ?int $excluindoId = null;

    /**
     * @return CursorPaginator<int, Organizacao>
     */
    #[Computed]
    public function organizacoes(): CursorPaginator
    {
        return Organizacao::paginarListagem();
    }

    /**
     * Mantém a listagem atualizando enquanto houver pedido na fila.
     */
    #[Computed]
    public function emAndamento(): bool
    {
        return Organizacao::existeEmAndamento();
    }

    #[Computed]
    public function detalhe(): ?Organizacao
    {
        if ($this->detalheId === null) {
            return null;
        }

        return Organizacao::comArquivos($this->detalheId);
    }

    public function novo(): void
    {
        $this->resetForm();
        $this->formAberto = true;
    }

    public function fecharForm(): void
    {
        $this->formAberto = false;
        $this->resetForm();
    }

    /**
     * Guarda os arquivos e coloca a organização na fila.
     */
    public function enviar(): void
    {
        $this->validate($this->regras(), $this->mensagens(), ['entidade' => 'entidade']);

        Organizacao::iniciar(auth()->user()?->id, $this->entidade, $this->arquivos);

        $this->fecharForm();
        unset($this->organizacoes, $this->emAndamento);

        Flux::toast(variant: 'success', text: 'Arquivos recebidos. A organização começa em instantes.');
    }

    public function verDetalhes(int $id): void
    {
        $this->detalheId = $id;
        unset($this->detalhe);
        $this->detalheAberto = true;
    }

    public function confirmarExclusao(int $id): void
    {
        $this->excluindoId = $id;
        $this->exclusaoAberta = true;
    }

    public function excluir(): void
    {
        Organizacao::query()->findOrFail($this->excluindoId)->excluirComArquivos();

        $this->exclusaoAberta = false;
        $this->excluindoId = null;

        Flux::toast(variant: 'success', text: 'Organização excluída.');
    }

    public function render(): View
    {
        return view('livewire.organizacoes');
    }

    /**
     * @return array<string, list<string>>
     */
    private function regras(): array
    {
        return [
            'entidade' => ['nullable', 'string', 'max:150'],
            'arquivos' => ['required', 'array', 'min:1'],
            'arquivos.*' => ['file', 'mimes:zip,pdf', 'max:'.config('organizador.upload_max_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensagens(): array
    {
        return [
            'arquivos.required' => 'Envie ao menos um zip ou PDF.',
            'arquivos.*.mimes' => 'Envie apenas arquivos .zip ou .pdf.',
            'arquivos.*.max' => 'Cada arquivo pode ter até '.intdiv((int) config('organizador.upload_max_kb'), 1024).' MB.',
        ];
    }

    private function resetForm(): void
    {
        $this->entidade = '';
        $this->arquivos = [];
        $this->resetValidation();
    }
}
