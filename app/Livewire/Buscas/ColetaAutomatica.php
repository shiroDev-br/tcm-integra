<?php

namespace App\Livewire\Buscas;

use App\Models\Configuracao;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Component;

class ColetaAutomatica extends Component
{
    /** Suggested hour when the collection is turned on before a time is chosen. */
    private const HORA_SUGERIDA = '07';

    /** Minute options, in steps of 5. */
    private const PASSO_MINUTOS = 5;

    public bool $ativa = false;

    public string $hora = '';

    public string $minuto = '00';

    public ?string $ultimaColeta = null;

    public function mount(): void
    {
        $this->preencher(Configuracao::atual());
    }

    /**
     * The switch saves on its own, like any toggle; a missing time gets the suggested one.
     */
    public function updatedAtiva(): void
    {
        if ($this->ativa && $this->hora === '') {
            $this->hora = self::HORA_SUGERIDA;
        }

        $this->salvar();
    }

    public function updatedHora(): void
    {
        $this->salvar();
    }

    public function updatedMinuto(): void
    {
        $this->salvar();
    }

    public function salvar(): void
    {
        $validado = $this->validate([
            'ativa' => ['required', 'boolean'],
            'hora' => ['required_if_accepted:ativa', 'nullable', 'in:'.implode(',', self::horas())],
            'minuto' => ['required', 'in:'.implode(',', self::minutos())],
        ], [
            'hora.required_if_accepted' => 'Escolha a hora da coleta automática.',
            'hora.in' => 'Escolha uma hora válida.',
            'minuto.*' => 'Escolha um minuto válido.',
        ]);

        $horario = $validado['hora'] ? "{$validado['hora']}:{$validado['minuto']}" : null;

        $this->preencher(Configuracao::agendarColeta($validado['ativa'], $horario));

        Flux::toast(variant: 'success', text: $this->ativa
            ? "Coleta automática agendada para todos os dias às {$horario}."
            : 'Coleta automática desativada.');
    }

    /**
     * @return list<string> 00..23
     */
    public static function horas(): array
    {
        return array_map(fn (int $hora): string => sprintf('%02d', $hora), range(0, 23));
    }

    /**
     * @return list<string> 00, 05, ..., 55
     */
    public static function minutos(): array
    {
        return array_map(fn (int $minuto): string => sprintf('%02d', $minuto), range(0, 59, self::PASSO_MINUTOS));
    }

    private function preencher(Configuracao $configuracao): void
    {
        [$hora, $minuto] = explode(':', $configuracao->horario_coleta ?? ':00');

        $this->ativa = $configuracao->coleta_automatica_ativa;
        $this->hora = $hora;
        $this->minuto = $minuto;
        $this->ultimaColeta = $configuracao->ultima_coleta_automatica;
    }

    public function render(): View
    {
        return view('livewire.buscas.coleta-automatica');
    }
}
