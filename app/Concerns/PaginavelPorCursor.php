<?php

namespace App\Concerns;

/**
 * Livewire listings paginated by cursor: changing a filter goes back to the first page,
 * since the current cursor belongs to the previous result set.
 *
 * The component must use WithPagination and list its filters in filtrosDaListagem().
 */
trait PaginavelPorCursor
{
    /**
     * Properties that change the result set.
     *
     * @return list<string>
     */
    abstract protected function filtrosDaListagem(): array;

    public function updated(string $property): void
    {
        if (! in_array($property, $this->filtrosDaListagem(), true)) {
            return;
        }

        $this->voltarAoInicioDaListagem();
    }

    protected function voltarAoInicioDaListagem(): void
    {
        $this->resetPage('cursor');
    }
}
