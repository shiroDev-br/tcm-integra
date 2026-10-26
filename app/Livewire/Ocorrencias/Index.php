<?php

namespace App\Livewire\Ocorrencias;

use App\Concerns\NavegavelPorTermo;
use App\Enums\OccurrenceType;
use App\Models\Occurrence;
use App\Models\Term;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Ocorrências')]
class Index extends Component
{
    use NavegavelPorTermo;
    use WithPagination;

    public string $tipo = '';

    public string $periodo_inicio = '';

    public string $periodo_fim = '';

    public string $busca_texto = '';

    /**
     * @return list<string>
     */
    protected function filtrosDoTermo(): array
    {
        return ['tipo', 'periodo_inicio', 'periodo_fim', 'busca_texto'];
    }

    /**
     * Cards: active terms with how many occurrences each one has.
     *
     * @return Collection<int, Term>
     */
    #[Computed]
    public function termos(): Collection
    {
        return Term::ativosComTotalDeOcorrencias();
    }

    /**
     * @return CursorPaginator<int, Occurrence>
     */
    #[Computed]
    public function ocorrencias(): CursorPaginator
    {
        return Occurrence::paginarListagem([
            'termo' => $this->filtroTermo,
            'tipo' => $this->tipo,
            'inicio' => $this->periodo_inicio,
            'fim' => $this->periodo_fim,
            'texto' => $this->busca_texto,
        ]);
    }

    public function render(): View
    {
        return view('livewire.ocorrencias', [
            'tipos' => OccurrenceType::cases(),
            'termoAtual' => $this->termoAtual(),
        ]);
    }
}
