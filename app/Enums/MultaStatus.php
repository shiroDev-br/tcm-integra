<?php

namespace App\Enums;

enum MultaStatus: string
{
    case Pendente = 'pendente';

    case Paga = 'paga';

    /**
     * Rótulo da situação exibido na interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Paga => 'Paga',
        };
    }

    /**
     * Cor do flux:badge correspondente à situação.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pendente => 'amber',
            self::Paga => 'green',
        };
    }
}
