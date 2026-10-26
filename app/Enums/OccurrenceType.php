<?php

namespace App\Enums;

enum OccurrenceType: string
{
    case Act = 'ato';

    case Mention = 'mencao';

    /**
     * Rótulo do tipo exibido na interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Act => 'Ato',
            self::Mention => 'Menção',
        };
    }

    /**
     * Cor do flux:badge correspondente ao tipo.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Act => 'violet',
            self::Mention => 'zinc',
        };
    }
}
