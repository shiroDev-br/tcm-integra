<?php

namespace App\Livewire\Buscas;

use App\Exceptions\SourceUnavailable;
use App\Models\SearchRun;
use App\Services\PeriodRange;
use App\Services\SearchService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Buscas')]

class Index extends Component
{
    public string $periodo = '7';

    public ?string $inicio = null;

    public ?string $fim = null;

    public function buscar(): void
    {
        $this->validate([
            'periodo' => ['sometimes', 'in:hoje,7,30'],
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $input = PeriodRange::fromInput([
                'periodo' => filled($this->inicio) || filled($this->fim) ? null : $this->periodo,
                'inicio' => $this->inicio,
                'fim' => $this->fim,
            ]);

            app(SearchService::class)->execute($input->toArray());

            Flux::toast(
                variant: 'success',
                text: 'Coleta concluída com sucesso.',
            );
        } catch (SourceUnavailable $e) {
            Flux::toast(
                variant: 'danger',
                text: $e->getMessage(),
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                text: 'Erro inesperado: '.$e->getMessage(),
            );
        }
    }

    /**
     * Últimas coletas registradas (audit trail da tabela `buscas`).
     *
     * @return Collection<int, SearchRun>
     */
    #[Computed]
    public function historico(): Collection
    {
        return SearchRun::query()->recentes()->take(10)->get();
    }

    public function render(): View
    {
        return view('livewire.buscas');
    }
}
