<?php

namespace App\Livewire\Processos;

use App\Exceptions\SourceUnavailable;
use App\Models\Prazo;
use App\Models\Process;
use App\Services\TcmProcessClient;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Process detail modal: portal data, deadlines (editable) and progress (refreshable).
 * Opened from any listing with the `abrir-processo` event.
 */
class Detalhe extends Component
{
    public bool $aberto = false;

    public ?string $numero = null;

    public ?int $prazoEmEdicao = null;

    public string $novoVencimento = '';

    #[On('abrir-processo')]
    public function abrir(string $numero): void
    {
        $this->cancelarEdicaoDoPrazo();
        $this->numero = $numero;
        $this->aberto = true;
    }

    public function fechar(): void
    {
        $this->cancelarEdicaoDoPrazo();
        $this->aberto = false;
        $this->numero = null;
    }

    /**
     * Process shown in the modal, with its term links and deadlines eager loaded.
     */
    private function processo(): ?Process
    {
        if ($this->numero === null) {
            return null;
        }

        return Process::comDetalhes($this->numero);
    }

    public function editarPrazo(int $prazoId): void
    {
        $prazo = $this->prazoDoProcesso($prazoId);

        $this->resetErrorBag();
        $this->prazoEmEdicao = $prazo->id;
        $this->novoVencimento = $prazo->data_vencimento;
    }

    public function cancelarEdicaoDoPrazo(): void
    {
        $this->resetErrorBag();
        $this->prazoEmEdicao = null;
        $this->novoVencimento = '';
    }

    public function salvarPrazo(): void
    {
        if ($this->prazoEmEdicao === null) {
            return;
        }

        $validado = $this->validate(
            ['novoVencimento' => ['required', 'date_format:Y-m-d']],
            ['novoVencimento.*' => 'Informe uma data de vencimento válida.'],
        );

        $this->prazoDoProcesso($this->prazoEmEdicao)->ajustarVencimento($validado['novoVencimento']);

        $this->cancelarEdicaoDoPrazo();
        $this->processoAlterado();

        Flux::toast(variant: 'success', text: 'Prazo atualizado.');
    }

    /**
     * Looks the process up again on the TCM portal (progress, status, author...).
     */
    public function buscarAndamento(TcmProcessClient $tcm): void
    {
        if ($this->numero === null) {
            return;
        }

        try {
            Process::atualizarDoPortal($this->numero, $tcm);
        } catch (SourceUnavailable $e) {
            Flux::toast(variant: 'danger', text: 'Não foi possível consultar o TCM agora: '.$e->getMessage());

            return;
        }

        $this->processoAlterado();

        Flux::toast(variant: 'success', text: 'Andamento atualizado com os dados do TCM.');
    }

    /**
     * Deadline being edited, scoped to the open process (ids from the browser are not trusted).
     */
    private function prazoDoProcesso(int $prazoId): Prazo
    {
        return Prazo::query()->where('processo_numero', $this->numero)->findOrFail($prazoId);
    }

    private function processoAlterado(): void
    {
        $this->dispatch('processo-atualizado', numero: $this->numero);
    }

    public function render(): View
    {
        return view('livewire.processos.detalhe', [
            'detalhe' => $this->processo(),
        ]);
    }
}
