<?php

namespace App\Services\Organizador;

use Illuminate\Support\Str;

/**
 * Normalização de texto extraído de PDF para comparar com regras (sem acento, maiúsculo, espaços simples).
 */
final class Texto
{
    /**
     * Títulos de documentos (capa, notas, contrato, inexigibilidade) ficam no início da página;
     * olhar só o cabeçalho evita que citações no corpo do texto confundam a classificação.
     */
    private const TAMANHO_DO_CABECALHO = 500;

    public static function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->upper()->squish()->toString();
    }

    public static function cabecalho(string $textoNormalizado): string
    {
        return substr($textoNormalizado, 0, self::TAMANHO_DO_CABECALHO);
    }

    /**
     * Whether the normalized text matches any of the patterns.
     *
     * @param  list<string>  $padroes
     */
    public static function contemAlgum(string $textoNormalizado, array $padroes): bool
    {
        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $textoNormalizado) === 1) {
                return true;
            }
        }

        return false;
    }
}
