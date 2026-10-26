<?php

namespace App\Concerns;

use App\Models\Term;
use Livewire\Attributes\Url;

/**
 * Livewire listings that open on term cards and drill down into one term.
 *
 * The component must use WithPagination and list its per-term filters in filtrosDoTermo().
 */
trait NavegavelPorTermo
{
    use PaginavelPorCursor;

    #[Url(as: 'termo')]
    public string $filtroTermo = '';

    /**
     * Filters cleared whenever the user opens a term or goes back to the cards.
     *
     * @return list<string>
     */
    abstract protected function filtrosDoTermo(): array;

    /**
     * @return list<string>
     */
    protected function filtrosDaListagem(): array
    {
        return ['filtroTermo', ...$this->filtrosDoTermo()];
    }

    /**
     * Term currently opened in the list view (null when showing the cards).
     */
    public function termoAtual(): ?Term
    {
        if ($this->filtroTermo === '') {
            return null;
        }

        return Term::query()->find((int) $this->filtroTermo);
    }

    public function abrirTermo(int $termoId): void
    {
        $this->limparFiltrosDoTermo();
        $this->filtroTermo = (string) $termoId;
    }

    public function voltarCards(): void
    {
        $this->limparFiltrosDoTermo();
        $this->filtroTermo = '';
    }

    private function limparFiltrosDoTermo(): void
    {
        $this->reset($this->filtrosDoTermo());
        $this->voltarAoInicioDaListagem();
    }
}
