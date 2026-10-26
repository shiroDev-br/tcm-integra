<?php

namespace App\Livewire\Edicoes;

use App\Concerns\PaginavelPorCursor;
use App\Models\Edition;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Edições')]
class Index extends Component
{
    use PaginavelPorCursor;
    use WithPagination;

    #[Url(as: 'q')]
    public string $busca = '';

    #[Url(as: 'inicio')]
    public string $inicio = '';

    #[Url(as: 'fim')]
    public string $fim = '';

    #[Url(as: 'suplemento')]
    public string $filtroSuplemento = '';

    /**
     * @return list<string>
     */
    protected function filtrosDaListagem(): array
    {
        return ['busca', 'inicio', 'fim', 'filtroSuplemento'];
    }

    /**
     * Paginated editions honoring the search and the URL filters.
     *
     * @return CursorPaginator<int, Edition>
     */
    #[Computed]
    public function edicoes(): CursorPaginator
    {
        return Edition::paginarListagem([
            'busca' => $this->busca,
            'inicio' => $this->inicio,
            'fim' => $this->fim,
            'suplemento' => $this->filtroSuplemento,
        ]);
    }

    public function render(): View
    {
        return view('livewire.edicoes');
    }
}
