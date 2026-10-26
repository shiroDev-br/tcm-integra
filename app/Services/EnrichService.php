<?php

namespace App\Services;

use App\Enums\OccurrenceType;
use Normalizer;

/**
 * Deterministic enrichment of search hits (no LLM): context, acts and classification.
 */
class EnrichService
{
    /** @var list<string> */
    private const ACT_PATTERNS = [
        '/(?:Processo|PROC)\s+(?:e-TCM|TCM(?:\/BA)?)?\s*n[º°o]\s*[\d.]+e\d+[A-Za-z]*/iu',
        '/Ac[óo]rd[ãa]o\s+n[º°o]\s*\d+e\d+[A-Za-z]*/iu',
        '/Preg[ãa]o\s+Eletr[ôo]nico\s+n[º°o]\s*\d+\/\d{4}/iu',
        '/Edital\s+n[º°o]\s*\d+\/\d{4}/iu',
        '/(?:Prefeitura|C[âa]mara)\s+Municipal\s+de\s+(?:[A-Za-zÀ-ÿ0-9\'’-]+\s+){0,3}[A-Za-zÀ-ÿ0-9\'’-]+/u',
    ];

    private const PROCESS_PATTERN = '/\b(\d{4,6}[e-]\d{2})[A-Za-z]{0,3}\b/u';

    private const TAG_PATTERN = '#</?strong>#i';

    /** A table row: the process number is followed by the "MM/AAAA a MM/AAAA" period column. */
    private const ROW_PATTERN = '/\G.{0,250}?\d{2}\/\d{4}\s+a\s+\d{2}\/\d{4}/su';

    /** @var array<string, string> base letter => the letter and its accented forms */
    private const ACCENT_CLASSES = [
        'a' => 'aáàâãä', 'e' => 'eéèêë', 'i' => 'iíìîï', 'o' => 'oóòôõö', 'u' => 'uúùûü', 'c' => 'cç', 'n' => 'nñ',
    ];

    /**
     * Lowercase + accents removed + collapsed spaces (same view as the source).
     */
    public static function normalize(string $text): string
    {
        [$chars] = self::normalizeChars($text);
        $colapsado = trim((string) preg_replace('/\s+/u', ' ', implode('', $chars)));

        return $colapsado;
    }

    /**
     * Per-character normalization keeping the map to the original positions.
     *
     * @return array{0: list<string>, 1: list<int>} normalized chars, original char index per char
     */
    private static function normalizeChars(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $saida = [];
        $mapa = [];

        foreach ($chars as $i => $ch) {
            $nfd = Normalizer::normalize($ch, Normalizer::FORM_D);

            foreach (preg_split('//u', $nfd !== false ? $nfd : $ch, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
                if (preg_match('/\p{M}/u', $c)) {
                    continue;
                }

                $saida[] = mb_strtolower($c, 'UTF-8');
                $mapa[] = $i;
            }
        }

        return [$saida, $mapa];
    }

    /**
     * Removes the highlight tags and collapses spaces.
     */
    public static function cleanHighlight(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace(self::TAG_PATTERN, ' ', $html)));
    }

    /**
     * Finds the anchor (accent/case insensitive) and cuts ±window around it.
     *
     * @return array{0: string, 1: int} [context, start position of the anchor (-1 when not found)]
     */
    public static function extractContext(string $content, string $anchor, ?int $window = null): array
    {
        $window ??= (int) config('tcm.context_chars');
        $pos = self::position($content, $anchor);

        if ($pos === null) {
            return [trim(mb_substr($content, 0, $window * 2, 'UTF-8')), -1];
        }

        [$inicio, $fim] = $pos;
        $start = max(0, $inicio - $window);

        return [trim(mb_substr($content, $start, $fim + $window - $start, 'UTF-8')), $inicio];
    }

    /**
     * Position [start, end) of the anchor in the original text (accent/case insensitive).
     *
     * @return array{0: int, 1: int}|null
     */
    private static function position(string $content, string $anchor): ?array
    {
        if ($anchor === '') {
            return null;
        }

        [$texto, $mapa] = self::normalizeChars($content);
        [$ancoraChars] = self::normalizeChars($anchor);

        // the anchor view is whitespace-collapsed (same as the original `normalizar`)
        $ancoraStr = trim((string) preg_replace('/\s+/u', ' ', implode('', $ancoraChars)));

        if ($ancoraStr === '') {
            return null;
        }

        $ancora = preg_split('//u', $ancoraStr, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $indice = self::findSequence($texto, $ancora);

        if ($indice === null && str_contains($ancoraStr, ' ')) {
            // fallback: only the first keyword
            $primeira = explode(' ', $ancoraStr, 2)[0];
            $ancora = preg_split('//u', $primeira, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $indice = self::findSequence($texto, $ancora);
        }

        if ($indice === null) {
            return null;
        }

        $inicioChar = $mapa[$indice];
        $fimChar = $mapa[$indice + count($ancora) - 1] + 1;

        return [$inicioChar, $fimChar];
    }

    /**
     * Char-index of the first occurrence of the sequence, or null.
     *
     * @param  list<string>  $texto
     * @param  list<string>  $sequencia
     */
    private static function findSequence(array $texto, array $sequencia): ?int
    {
        $n = count($sequencia);
        $total = count($texto);

        for ($i = 0; $i + $n <= $total; $i++) {
            $bate = true;

            for ($j = 0; $j < $n; $j++) {
                if ($texto[$i + $j] !== $sequencia[$j]) {
                    $bate = false;
                    break;
                }
            }

            if ($bate) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Word-boundary occurrence count of the term on the page.
     */
    public static function countOnPage(string $content, string $term): int
    {
        $padrao = self::termPattern($term);

        if ($padrao === null) {
            return 0;
        }

        return preg_match_all($padrao, $content) ?: 0;
    }

    /**
     * Regex matching the whole term in the original text, ignoring case and accents
     * ("Itajuípe" matches "ITAJUIPE"); null for an empty term.
     */
    public static function termPattern(string $term): ?string
    {
        $normalizado = self::normalize($term);

        if ($normalizado === '') {
            return null;
        }

        $partes = array_map(
            fn (string $ch): string => match (true) {
                $ch === ' ' => '\s+',
                isset(self::ACCENT_CLASSES[$ch]) => '['.self::ACCENT_CLASSES[$ch].']',
                default => preg_quote($ch, '/'),
            },
            preg_split('//u', $normalizado, -1, PREG_SPLIT_NO_EMPTY) ?: []
        );

        return '/(?<![\p{L}\p{N}])'.implode('', $partes).'(?![\p{L}\p{N}])/iu';
    }

    /**
     * End offset of the table row started by the process number ending at $fimDoNumero
     * (row = number followed by its "MM/AAAA a MM/AAAA" period); null when it is not a row.
     */
    public static function tableRowEnd(string $content, int $fimDoNumero): ?int
    {
        if (preg_match(self::ROW_PATTERN, $content, $match, 0, $fimDoNumero) !== 1) {
            return null;
        }

        return $fimDoNumero + strlen($match[0]);
    }

    /**
     * Byte slice (offsets come from preg) repaired at the edges if it cut a multibyte char.
     */
    public static function byteSlice(string $content, int $inicio, int $fim): string
    {
        $inicio = max(0, $inicio);

        return mb_scrub(substr($content, $inicio, max(0, $fim - $inicio)), 'UTF-8');
    }

    /**
     * Unique acts present in the window, in appearance order.
     *
     * @return list<string>
     */
    public static function extractActs(string $window): array
    {
        $achados = [];

        foreach (self::ACT_PATTERNS as $padrao) {
            if (! preg_match_all($padrao, $window, $matches)) {
                continue;
            }

            foreach ($matches[0] as $trecho) {
                $trecho = trim((string) preg_replace('/\s+/u', ' ', $trecho));

                if (! in_array($trecho, $achados, true)) {
                    $achados[] = $trecho;
                }
            }
        }

        return $achados;
    }

    /**
     * TCM process numbers (base without suffix) in lowercase, unique, in order.
     *
     * @return list<string>
     */
    public static function extractProcessNumbers(string $window): array
    {
        $vistos = [];

        foreach (self::processNumberPositions($window) as [$numero]) {
            if (! in_array($numero, $vistos, true)) {
                $vistos[] = $numero;
            }
        }

        return $vistos;
    }

    /**
     * Every TCM process number (lowercase base) with its byte span in the text, in order.
     *
     * @return list<array{0: string, 1: int, 2: int}> [number, start, end)
     */
    public static function processNumberPositions(string $text): array
    {
        if (! preg_match_all(self::PROCESS_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return array_map(
            fn (array $match): array => [strtolower($match[0]), $match[1], $match[1] + strlen($match[0])],
            $matches[1]
        );
    }

    /**
     * @param  list<string>  $acts
     */
    public static function classify(array $acts): OccurrenceType
    {
        return $acts === [] ? OccurrenceType::Mention : OccurrenceType::Act;
    }

    public static function pdfUrl(int $editionId, ?int $page = null): string
    {
        $base = rtrim((string) config('tcm.base_url'), '/');
        $subtema = trim((string) config('tcm.subtheme'), '/');
        $url = "{$base}/{$subtema}/portal/edicoes/download/{$editionId}";

        return $page ? "{$url}/{$page}" : $url;
    }

    /**
     * Turns a source hit into an occurrence ready to persist. `processos` are only candidates
     * (see `evidencias`): SearchService keeps the ones the TCM portal does not contradict.
     *
     * @param  array<string, mixed>  $hit
     * @return array{edicao_id: int, pagina: int, trecho: string, contexto: string, atos: list<string>, processos: list<string>, evidencias: array<string, Evidencia>, tipo: OccurrenceType, ocorrencias_na_pagina: int, pdf_url: string, hit_id: string}
     */
    public static function enrich(array $hit, string $term): array
    {
        $fonte = $hit['_source'] ?? [];
        $conteudo = (string) ($fonte['conteudo'] ?? '');
        $edicaoId = (int) ($fonte['diario_id'] ?? 0);
        $pagina = (int) ($fonte['pagina'] ?? 0);

        $destaque = $hit['highlight']['conteudo'] ?? [];
        $trecho = is_array($destaque) && isset($destaque[0]) ? self::cleanHighlight((string) $destaque[0]) : '';

        // anchored on the term itself: the highlight often starts in a neighbouring record
        [$contexto, $pos] = self::extractContext($conteudo, $term);
        $janela = (int) config('tcm.act_window_chars');

        if ($pos >= 0 && $conteudo !== '') {
            $inicioJanela = max(0, $pos - $janela);
            $janelaAtos = mb_substr($conteudo, $inicioJanela, $pos + $janela - $inicioJanela, 'UTF-8');
        } else {
            $janelaAtos = mb_substr($conteudo, 0, $janela * 2, 'UTF-8');
        }

        $atos = self::extractActs($janelaAtos);
        $evidencias = LocalizadorDeProcessos::evidencias($conteudo, $term);

        return [
            'edicao_id' => $edicaoId,
            'pagina' => $pagina,
            'trecho' => $trecho !== '' ? $trecho : mb_substr($contexto, 0, 200, 'UTF-8'),
            'contexto' => $contexto,
            'atos' => $atos,
            'processos' => array_keys($evidencias),
            'evidencias' => $evidencias,
            'tipo' => self::classify($atos),
            'ocorrencias_na_pagina' => self::countOnPage($conteudo, $term),
            'pdf_url' => self::pdfUrl($edicaoId, $pagina),
            'hit_id' => (string) ($hit['_id'] ?? ''),
        ];
    }
}
