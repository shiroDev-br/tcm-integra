<?php

namespace App\Services\Organizador;

use Illuminate\Support\Str;

/**
 * Regras de nome dos arquivos entregues ao TCM ("PP 202501220006 COELBA", "INEX 001-2025 - ACJUS - Parte 1").
 */
final class NomeDeArquivo
{
    /** Número do processo de pagamento: AAAAMMDD + sequência de 4 dígitos. */
    public const NUMERO_PROCESSO = '/(?<!\d)(20\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])\d{4})(?!\d)/';

    private const PARTE = '/\s*-?\s*PARTE\s*(\d+)\s*$/i';

    /** Primeiras palavras que não identificam o fornecedor sozinhas. */
    private const PALAVRAS_GENERICAS = [
        'ASSOCIACAO', 'BANCO', 'CAMARA', 'COMPANHIA', 'CONSELHO', 'COOPERATIVA', 'EMPRESA',
        'FOLHA', 'FUNDO', 'INSTITUTO', 'MUNICIPIO', 'PREFEITURA', 'SECRETARIA', 'SINDICATO',
    ];

    public static function numeroDoProcesso(string $texto): ?string
    {
        if (preg_match(self::NUMERO_PROCESSO, $texto, $numero) !== 1) {
            return null;
        }

        return $numero[1];
    }

    /**
     * Nome já no padrão "PP|PPE <número> ..." para o número dado.
     */
    public static function isNomeDeProcesso(string $nome, string $numero): bool
    {
        return preg_match('/^PPE?\s+'.preg_quote($numero, '/').'\b/i', trim($nome)) === 1;
    }

    /**
     * Nome curto do fornecedor: "27 - COELBA - COMPANHIA DE ..." vira "COELBA"; nomes genéricos
     * ("SECRETARIA MUNICIPAL DE ...") ficam inteiros até o limite configurado.
     */
    public static function fornecedorCurto(string $fornecedor): string
    {
        $semCodigo = (string) preg_replace('/^\d+\s*[-.]\s*/', '', Texto::normalizar($fornecedor));
        $principal = trim(explode(' - ', $semCodigo)[0]);
        $limpo = Str::squish((string) preg_replace('/[^A-Z0-9 ]/', ' ', $principal));
        $primeira = Str::before($limpo, ' ');

        if (! in_array($primeira, self::PALAVRAS_GENERICAS, true)) {
            return $primeira;
        }

        return Str::squish(Str::limit($limpo, (int) config('organizador.tamanho_nome_fornecedor'), '', preserveWords: true));
    }

    /**
     * Número da parte ("... - Parte 2" → 2), ou null quando o nome não indica parte.
     */
    public static function parte(string $nome): ?int
    {
        if (preg_match(self::PARTE, $nome, $parte) !== 1) {
            return null;
        }

        return (int) $parte[1];
    }

    public static function semParte(string $nome): string
    {
        return trim((string) preg_replace(self::PARTE, '', $nome));
    }

    public static function comParte(string $nome, int $parte): string
    {
        return self::semParte($nome).' - Parte '.$parte;
    }

    /**
     * Remove caracteres proibidos em nomes de arquivo no Windows e no zip.
     */
    public static function seguro(string $nome): string
    {
        $limpo = Str::squish((string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', ' ', $nome));

        return $limpo === '' ? 'documento' : $limpo;
    }
}
