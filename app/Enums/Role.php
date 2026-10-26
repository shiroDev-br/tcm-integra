<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'superadmin';

    case Admin = 'admin';

    /**
     * Rótulo do papel exibido na interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadmin',
            self::Admin => 'Admin',
        };
    }

    /**
     * Cor do flux:badge correspondente ao papel.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'violet',
            self::Admin => 'sky',
        };
    }
}
