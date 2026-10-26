<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum ContagemDePrazo: string
{
    case Corridos = 'corridos';

    case Uteis = 'uteis';

    /**
     * Rótulo da contagem exibido na interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Corridos => 'dias corridos',
            self::Uteis => 'dias úteis',
        };
    }

    /**
     * Due date counting from the day after the start; a deadline ending on a weekend
     * moves to the next weekday (holidays are not known, see Prazo::$estimado).
     */
    public function vencimento(CarbonImmutable $inicio, int $dias): CarbonImmutable
    {
        if ($this === self::Uteis) {
            return $inicio->addWeekdays($dias);
        }

        $vencimento = $inicio->addDays($dias);

        if (! $vencimento->isWeekend()) {
            return $vencimento;
        }

        return $vencimento->nextWeekday();
    }
}
