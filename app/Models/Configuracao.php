<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * System-wide settings, kept in a single row.
 *
 * @property int $id
 * @property bool $coleta_automatica_ativa
 * @property string|null $horario_coleta HH:MM in the TCM timezone
 * @property string|null $ultima_coleta_automatica Y-m-d of the last automatic collection
 */
#[Fillable(['coleta_automatica_ativa', 'horario_coleta'])]
class Configuracao extends Model
{
    protected $table = 'configuracoes';

    /** @var array<string, mixed> */
    protected $attributes = [
        'coleta_automatica_ativa' => false,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'coleta_automatica_ativa' => 'boolean',
    ];

    /**
     * The settings row, created with defaults on first access.
     */
    public static function atual(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Saves the daily automatic collection schedule.
     */
    public static function agendarColeta(bool $ativa, ?string $horario): self
    {
        $configuracao = static::atual();
        $configuracao->update(['coleta_automatica_ativa' => $ativa, 'horario_coleta' => $horario]);

        return $configuracao;
    }

    /**
     * Whether today's automatic collection is due: enabled, time reached and not run yet today.
     * Checking "time reached" (not "exact minute") lets a missed minute still run later that day.
     */
    public function coletaPendente(string $hoje, string $agora): bool
    {
        if (! $this->coleta_automatica_ativa || $this->horario_coleta === null) {
            return false;
        }

        if ($this->ultima_coleta_automatica === $hoje) {
            return false;
        }

        return $agora >= $this->horario_coleta;
    }

    /**
     * Atomically claims today's automatic collection; false when another run already claimed it.
     */
    public function reservarColetaDoDia(string $hoje): bool
    {
        $reservada = static::query()
            ->whereKey($this->id)
            ->where(fn ($query) => $query->whereNull('ultima_coleta_automatica')->orWhere('ultima_coleta_automatica', '!=', $hoje))
            ->update(['ultima_coleta_automatica' => $hoje]) === 1;

        $this->refresh();

        return $reservada;
    }
}
