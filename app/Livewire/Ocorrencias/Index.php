<?php

namespace App\Livewire\Ocorrencias;

use App\Concerns\NavegavelPorTermo;
use App\Enums\OccurrenceType;
use App\Models\Occurrence;
use App\Models\Process;
use App\Models\SearchRun;
use App\Models\Term;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Occurrences and processes of a term on one page, one tab each.
 */
#[Title('Ocorrências e processos')]
class Index extends Component
{
    use NavegavelPorTermo;
    use WithPagination;

    public const ABA_OCORRENCIAS = 'ocorrencias';

    public const ABA_PROCESSOS = 'processos';

    #[Url]
    public string $aba = self::ABA_OCORRENCIAS;

    public string $tipo = '';

    public string $periodo_inicio = '';

    public string $periodo_fim = '';

    public string $busca_texto = '';

    #[Url(as: 'q')]
    public string $busca = '';

    #[Url(as: 'situacao')]
    public string $filtroSituacao = '';

    /**
     * @return list<string>
     */
    protected function filtrosDoTermo(): array
    {
        return ['tipo', 'periodo_inicio', 'periodo_fim', 'busca_texto', 'busca', 'filtroSituacao'];
    }

    /**
     * Arriving with ?termo= (e.g. from the dashboard notice) also counts as opening it.
     */
    public function mount(): void
    {
        if ($this->filtroTermo === '') {
            return;
        }

        $this->aoAbrirTermo((int) $this->filtroTermo);
    }

    protected function aoAbrirTermo(int $termoId): void
    {
        Auth::user()?->marcarTermoComoVisto($termoId);
    }

    /**
     * Each tab has its own result set, so its cursor starts over; unknown tabs fall back to occurrences.
     */
    public function updatedAba(): void
    {
        if ($this->aba !== self::ABA_PROCESSOS) {
            $this->aba = self::ABA_OCORRENCIAS;
        }

        $this->voltarAoInicioDaListagem();
    }

    /**
     * Cards: active terms with how many occurrences and processes each one has.
     *
     * @return Collection<int, Term>
     */
    #[Computed]
    public function termos(): Collection
    {
        return Term::ativosComTotais();
    }

    /**
     * Ids of the terms today's collection found that the user has not opened yet.
     *
     * @return list<int>
     */
    #[Computed]
    public function encontradosHoje(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        return SearchRun::daColetaDeHoje()?->termosNaoVistosPor($user) ?? [];
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

    /**
     * Processes cited by the term, closest deadline first.
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

    /**
     * The detail modal changed a process (deadline or progress): render the listing again.
     */
    #[On('processo-atualizado')]
    public function recarregar(): void
    {
        unset($this->processos);
    }

    public function render(): View
    {
        $termoAtual = $this->termoAtual();

        return view('livewire.ocorrencias', [
            'tipos' => OccurrenceType::cases(),
            'termoAtual' => $termoAtual,
            ...($termoAtual === null ? $this->cardsSeparados() : []),
        ]);
    }

    /**
     * Term cards split between "found today" (not opened yet by this user) and the rest.
     *
     * @return array{termosNovos: Collection<int, Term>, termosDemais: Collection<int, Term>}
     */
    private function cardsSeparados(): array
    {
        $novos = $this->encontradosHoje();
        [$termosNovos, $termosDemais] = $this->termos()->partition(
            fn (Term $termo): bool => in_array($termo->id, $novos, true)
        );

        return ['termosNovos' => $termosNovos, 'termosDemais' => $termosDemais];
    }
}
