<?php

namespace App\Enums;

enum TermType: string
{
    case Municipio = 'municipio';

    case Empresa = 'empresa';

    /**
     * Rótulo do tipo exibido na interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Municipio => 'Município',
            self::Empresa => 'Empresa',
        };
    }

    /**
     * Cor do flux:badge correspondente ao tipo.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Municipio => 'sky',
            self::Empresa => 'amber',
        };
    }

    /**
     * Ícone (Heroicons) que representa o tipo.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Municipio => 'building-library',
            self::Empresa => 'building-office-2',
        };
    }
}
