<?php

namespace App\Livewire\Dashboard;

use App\Models\Multa;
use App\Models\Occurrence;
use App\Models\Process;
use App\Models\SearchRun;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Lazy]
#[Title('Painel')]
class Index extends Component
{
    /**
     * Skeleton exibido enquanto os indicadores carregam.
     */
    public function placeholder(): View
    {
        return view('livewire.dashboard.placeholder');
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'totalTermosAtivos' => $this->totalTermosAtivos(),
            'totalOcorrencias' => $this->totalOcorrencias(),
            'totalProcessos' => $this->totalProcessos(),
            'multasPendentes' => $this->multasPendentes(),
            'ultimaColeta' => $this->ultimaColeta(),
            'ultimasBuscas' => $this->ultimasBuscas(),
        ]);
    }

    public function totalTermosAtivos(): int
    {
        return Term::query()->ativo()->count();
    }

    public function totalOcorrencias(): int
    {
        return Occurrence::count();
    }

    public function totalProcessos(): int
    {
        return Process::count();
    }

    public function multasPendentes(): int
    {
        return Multa::query()->pendente()->count();
    }

    public function ultimaColeta(): ?SearchRun
    {
        return SearchRun::query()->recentes()->first();
    }

    /**
     * @return Collection<int, SearchRun>
     */
    public function ultimasBuscas(): Collection
    {
        return SearchRun::query()->recentes()->take(5)->get();
    }
}
