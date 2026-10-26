<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Display format for dates coming from the database (dd/mm/aaaa).
 *
 * Date-only values are formatted as they are; values with a time are stored in UTC
 * and are shown in the TCM-BA timezone.
 */
final class FormatadorDeData
{
    private const FORMATO_DATA = 'd/m/Y';

    private const FORMATO_DATA_HORA = 'd/m/Y H:i';

    private const VAZIO = '—';

    /**
     * Date as dd/mm/aaaa.
     */
    public static function data(CarbonInterface|string|null $valor): string
    {
        if (blank($valor)) {
            return self::VAZIO;
        }

        if (is_string($valor) && self::estaNoFormatoBrasileiro($valor)) {
            return $valor;
        }

        return rescue(
            fn (): string => CarbonImmutable::parse($valor)->format(self::FORMATO_DATA),
            (string) $valor,
            report: false,
        );
    }

    /**
     * Date and time as dd/mm/aaaa hh:mm in the TCM-BA timezone.
     */
    public static function dataHora(CarbonInterface|string|null $valor): string
    {
        if (blank($valor)) {
            return self::VAZIO;
        }

        if (is_string($valor) && self::estaNoFormatoBrasileiro($valor)) {
            return $valor;
        }

        return rescue(
            fn (): string => CarbonImmutable::parse($valor)
                ->setTimezone(config('tcm.timezone'))
                ->format(self::FORMATO_DATA_HORA),
            (string) $valor,
            report: false,
        );
    }

    /**
     * Date range as "dd/mm/aaaa a dd/mm/aaaa".
     */
    public static function periodo(CarbonInterface|string|null $inicio, CarbonInterface|string|null $fim): string
    {
        return self::data($inicio).' a '.self::data($fim);
    }

    /**
     * Converts a dd/mm/aaaa text to aaaa-mm-dd; any other text is returned untouched.
     */
    public static function paraIso(string $valor): string
    {
        if (! self::estaNoFormatoBrasileiro($valor)) {
            return $valor;
        }

        return CarbonImmutable::createFromFormat('!d/m/Y', $valor)->format('Y-m-d');
    }

    private static function estaNoFormatoBrasileiro(string $valor): bool
    {
        return preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor) === 1;
    }
}
