<?php

namespace App\Enums;

enum PeriodPreset: string
{
    case Today = 'hoje';

    case Last7Days = '7';

    case Last30Days = '30';

    public function days(): int
    {
        return match ($this) {
            self::Today => 1,
            self::Last7Days => 7,
            self::Last30Days => 30,
        };
    }
}
