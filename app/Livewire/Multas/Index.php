<?php

namespace App\Livewire\Multas;

use App\Concerns\MultaValidationRules;
use App\Concerns\PaginavelPorCursor;
use App\Enums\MultaStatus;
use App\Models\Multa;
use App\Models\Term;
use Flux\Flux;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Multas')]
class Index extends Component
{
    use MultaValidationRules;
    use PaginavelPorCursor;
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q')]
    public string $busca = '';

    /** Term filter from the URL (?termo=), used by the future processes listing shortcut. */
    #[Url(as: 'termo')]
    public string $filtroTermo = '';

    #[Url(as: 'situacao')]
    public string $filtroSituacao = '';

    public bool $formAberto = false;

    public ?int $multaId = null;

    /** Empty string when no municipality is selected yet. */
    public string $termoId = '';

    public string $gestor = '';

    public string $processoNumero = '';

    public string $dataMulta = '';

    public string $dataVencimento = '';

    public string $valor = '';

    public string $situacao = 'pendente';

    /** @var TemporaryUploadedFile|null */
    public $comprovante = null;

    public ?string $comprovanteAtual = null;

    public bool $exclusaoAberta = false;

    public ?int $excluindoId = null;

    /**
     * Active municipalities shown in the form and in the filter.
     *
     * @return Collection<int, Term>
     */
    #[Computed]
    public function municipios(): Collection
    {
        return Term::municipiosAtivos();
    }

    /**
     * @return list<string>
     */
    protected function filtrosDaListagem(): array
    {
        return ['busca', 'filtroTermo', 'filtroSituacao'];
    }

    /**
     * Paginated fines honoring the search and the URL filters.
     *
     * @return CursorPaginator<int, Multa>
     */
    #[Computed]
    public function multas(): CursorPaginator
    {
        return Multa::paginarListagem([
            'busca' => $this->busca,
            'termo' => $this->filtroTermo,
            'situacao' => $this->filtroSituacao,
        ]);
    }

    /**
     * Open the form ready for a new fine.
     */
    public function criar(): void
    {
        $this->resetForm();
        $this->formAberto = true;
    }

    /**
     * Open the form filled with an existing fine.
     */
    public function editar(int $id): void
    {
        $multa = Multa::query()->findOrFail($id);

        $this->resetForm();

        $this->multaId = $multa->id;
        $this->termoId = (string) $multa->termo_id;
        $this->gestor = $multa->gestor;
        $this->processoNumero = $multa->processo_numero;
        $this->dataMulta = $multa->data_multa->format('Y-m-d');
        $this->dataVencimento = $multa->data_vencimento->format('Y-m-d');
        $this->valor = (string) $multa->valor;
        $this->situacao = $multa->situacao->value;
        $this->comprovanteAtual = $multa->comprovante;
        $this->formAberto = true;
    }

    /**
     * Close the form and clear its fields.
     */
    public function fecharForm(): void
    {
        $this->formAberto = false;
        $this->resetForm();
    }

    /**
     * Create or update the fine behind the open form.
     */
    public function salvar(): void
    {
        $validado = $this->validate($this->multaRules(), $this->multaMessages(), $this->multaAttributes());

        $editando = $this->multaId !== null;
        $multa = $editando ? Multa::query()->findOrFail($this->multaId) : new Multa;
        $arquivo = $this->comprovante;

        $dados = [
            'termo_id' => (int) $validado['termoId'],
            'gestor' => $validado['gestor'],
            'processo_numero' => $validado['processoNumero'],
            'data_multa' => $validado['dataMulta'],
            'data_vencimento' => $validado['dataVencimento'],
            'valor' => $validado['valor'],
            'situacao' => $validado['situacao'],
        ];

        if ($arquivo !== null) {
            if ($multa->comprovante !== null) {
                Storage::disk('local')->delete($multa->comprovante);
            }

            $dados['comprovante'] = $arquivo->store('multas', 'local');
        }

        $multa->fill($dados);
        $multa->save();

        $this->fecharForm();

        Flux::toast(
            variant: 'success',
            text: $editando ? 'Multa atualizada com sucesso.' : 'Multa cadastrada com sucesso.',
        );
    }

    /**
     * Ask for confirmation before deleting a fine.
     */
    public function confirmarExclusao(int $id): void
    {
        $this->excluindoId = $id;
        $this->exclusaoAberta = true;
    }

    /**
     * Delete the confirmed fine and its receipt.
     */
    public function excluir(): void
    {
        $multa = Multa::query()->findOrFail($this->excluindoId);

        if ($multa->comprovante !== null) {
            Storage::disk('local')->delete($multa->comprovante);
        }

        $multa->delete();

        $this->exclusaoAberta = false;
        $this->excluindoId = null;

        Flux::toast(variant: 'success', text: 'Multa excluída com sucesso.');
    }

    /**
     * Reset every form field.
     */
    private function resetForm(): void
    {
        $this->multaId = null;
        $this->termoId = '';
        $this->gestor = '';
        $this->processoNumero = '';
        $this->dataMulta = '';
        $this->dataVencimento = '';
        $this->valor = '';
        $this->situacao = MultaStatus::Pendente->value;
        $this->comprovante = null;
        $this->comprovanteAtual = null;
    }
}
