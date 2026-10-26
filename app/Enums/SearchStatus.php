<?php

namespace App\Enums;

enum SearchStatus: string
{
    case Ok = 'ok';

    case Partial = 'parcial';

    case Error = 'erro';

    /**
     * Rótulo da situação exibido na interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Concluída',
            self::Partial => 'Parcial',
            self::Error => 'Erro',
        };
    }

    /**
     * Cor do flux:badge correspondente à situação.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Ok => 'green',
            self::Partial => 'amber',
            self::Error => 'red',
        };
    }

    /**
     * Ícone (Heroicons) que representa a situação.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Ok => 'check-circle',
            self::Partial => 'exclamation-triangle',
            self::Error => 'x-circle',
        };
    }
}
