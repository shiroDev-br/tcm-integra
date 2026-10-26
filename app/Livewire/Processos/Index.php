<?php

namespace App\Livewire\Processos;

use App\Concerns\NavegavelPorTermo;
use App\Models\Process;
use App\Models\Term;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Processos')]
class Index extends Component
{
    use NavegavelPorTermo;
    use WithPagination;

    #[Url(as: 'q')]
    public string $busca = '';

    #[Url(as: 'situacao')]
    public string $filtroSituacao = '';

    public bool $detalheAberto = false;

    public ?string $processoDetalhe = null;

    /**
     * @return list<string>
     */
    protected function filtrosDoTermo(): array
    {
        return ['busca', 'filtroSituacao'];
    }

    /**
     * Cards: active terms with how many distinct processes each one has.
     *
     * @return Collection<int, Term>
     */
    #[Computed]
    public function termos(): Collection
    {
        return Term::ativosComTotalDeProcessos();
    }

    /**
     * Paginated processes honoring the search and the URL filters.
     *
     * @return CursorPaginator<int, Process>
     */
    #[Computed]
    public function processos(): CursorPaginator
    {
        return Process::paginarListagem([
            'busca' => $this->busca,
            'termo' => $this->filtroTermo,
            'situacao' => $this->filtroSituacao,
        ]);
    }

    public function verDetalhe(string $numero): void
    {
        $this->processoDetalhe = $numero;
        $this->detalheAberto = true;
    }

    public function fecharDetalhe(): void
    {
        $this->detalheAberto = false;
        $this->processoDetalhe = null;
    }

    /**
     * Process shown in the detail modal, with its term links and deadlines eager loaded.
     */
    private function detalhe(): ?Process
    {
        if ($this->processoDetalhe === null) {
            return null;
        }

        return Process::comDetalhes($this->processoDetalhe);
    }

    public function render(): View
    {
        return view('livewire.processos', [
            'detalhe' => $this->detalhe(),
            'termoAtual' => $this->termoAtual(),
        ]);
    }
}
