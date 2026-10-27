<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Time helpers: UTC instants for storage, TCM-BA timezone for business dates.
 */
final class Clock
{
    /**
     * Current instant in UTC (ISO 8601, second precision) — storage format.
     */
    public static function nowUtcIso(): string
    {
        return gmdate('Y-m-d\TH:i:s+00:00');
    }

    /**
     * Instant N minutes from now in UTC (ISO 8601, second precision).
     */
    public static function nowUtcIsoWithMinutes(int $minutes): string
    {
        return gmdate('Y-m-d\TH:i:s+00:00', time() + $minutes * 60);
    }

    /**
     * Current instant in the TCM timezone (ISO 8601, second precision).
     */
    public static function nowInTimezoneIso(): string
    {
        return self::now()->format('Y-m-d\TH:i:sP');
    }

    /**
     * Current business date (Y-m-d) in the TCM timezone.
     */
    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    /**
     * Current wall-clock time (H:i) in the TCM timezone.
     */
    public static function currentTime(): string
    {
        return self::now()->format('H:i');
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('tcm.timezone')));
    }
}
