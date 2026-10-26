<?php

namespace App\Services;

use App\Enums\EvidenciaDoVinculo;

/**
 * Finds which process numbers on a Diário Oficial page belong to the searched term.
 *
 * A page lists processes of many municipalities (Inspetoria tables, session agendas,
 * draws), so "a number close to the term" is not enough. Each number gets its own record:
 *  - table rows: from the number to its "MM/AAAA a MM/AAAA" period column;
 *  - acts: from the number to the next number (capped by the configured window).
 * The term inside the record is strong evidence; the term only right before the number
 * (outside tables) is weak evidence, kept only if the TCM portal confirms it.
 */
final class LocalizadorDeProcessos
{
    /**
     * @return array<string, Evidencia> process number => strongest evidence, in page order
     */
    public static function evidencias(string $conteudo, string $termo): array
    {
        $padrao = EnrichService::termPattern($termo);

        if ($padrao === null || $conteudo === '') {
            return [];
        }

        $numeros = EnrichService::processNumberPositions($conteudo);
        $evidencias = [];

        foreach ($numeros as $i => [$numero, $inicio, $fim]) {
            $evidencia = self::evidencia(
                $conteudo,
                $padrao,
                $inicio,
                $fim,
                $numeros[$i - 1][2] ?? 0,
                $numeros[$i + 1][1] ?? strlen($conteudo),
            );

            if ($evidencia === null || ($evidencias[$numero] ?? null)?->forca === EvidenciaDoVinculo::Forte) {
                continue;
            }

            $evidencias[$numero] = $evidencia;
        }

        return $evidencias;
    }

    private static function evidencia(string $conteudo, string $padrao, int $inicio, int $fim, int $fimDoAnterior, int $inicioDoProximo): ?Evidencia
    {
        $janela = (int) config('tcm.link_window_chars');
        $fimDaLinha = EnrichService::tableRowEnd($conteudo, $fim);

        if ($fimDaLinha !== null) {
            return self::evidenciaDaLinha($conteudo, $padrao, $fim, $fimDaLinha, min($inicioDoProximo, $fimDaLinha + $janela));
        }

        return self::trechoQueMenciona($conteudo, $padrao, EvidenciaDoVinculo::Forte, $fim, min($inicioDoProximo, $fim + $janela))
            ?? self::trechoQueMenciona($conteudo, $padrao, EvidenciaDoVinculo::Fraca, max($fimDoAnterior, $inicio - $janela), $inicio);
    }

    /**
     * In a table the text before the number is the previous row. The text after the period
     * column may be a wrapped cell of this row ("... 01/2026 a 04/2026 de LAURO DE FREITAS")
     * or, with the PDF columns interleaved, the next row: hence only weak evidence.
     */
    private static function evidenciaDaLinha(string $conteudo, string $padrao, int $fim, int $fimDaLinha, int $fimDaCauda): ?Evidencia
    {
        return self::trechoQueMenciona($conteudo, $padrao, EvidenciaDoVinculo::Forte, $fim, min($fimDaLinha, $fimDaCauda))
            ?? self::trechoQueMenciona($conteudo, $padrao, EvidenciaDoVinculo::Fraca, $fimDaLinha, $fimDaCauda);
    }

    private static function trechoQueMenciona(string $conteudo, string $padrao, EvidenciaDoVinculo $forca, int $inicio, int $fim): ?Evidencia
    {
        $trecho = EnrichService::byteSlice($conteudo, $inicio, $fim);

        if (preg_match($padrao, $trecho) !== 1) {
            return null;
        }

        return new Evidencia($forca, $trecho);
    }
}
