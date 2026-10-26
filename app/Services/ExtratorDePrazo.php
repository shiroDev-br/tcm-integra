<?php

namespace App\Services;

use App\Enums\ContagemDePrazo;

/**
 * Deterministic extraction of response deadlines from a Diário Oficial page (no LLM).
 *
 * The TCM-BA process portal does not report deadlines; they are only published in the
 * acts that notify/cite the parties. Two layouts are recognized:
 *  - nominal acts: "... no prazo (regimental) de 20 (vinte) dias corridos, contados a partir
 *    da publicação deste edital ... Processo e-TCM nº 22623e23" (clause and number close together);
 *  - Inspetoria notification tables (PROC Nº / GESTOR / ENTIDADE / PERÍODO rows), whose clause sits
 *    in the edital header, often on a previous page.
 *
 * The source text interleaves the PDF columns, so acts cannot be delimited reliably; the
 * association relies on proximity and on the row layout instead.
 */
final class ExtratorDePrazo
{
    /** "prazo (regimental) de 20 (vinte) dias (corridos)" or an extension: "concedendo mais 10 (dez) dias". */
    private const CLAUSE_PATTERN = '/(?:prazo\s+(?:regimental\s+|legal\s+)?(?:de\s+)?(?:at[ée]\s+)?|conced\w*\s+mais\s+)'
        .'(?<dias>\d{1,3}|quarenta\s+e\s+cinco|um|dois|tr[êe]s|quatro|cinco|seis|sete|oito|nove|dez|quinze|vinte|trinta|quarenta|sessenta|noventa)'
        .'\s*(?:\([^)]{1,30}\)\s*)?dias?(?<uteis>\s+[úu]teis)?/iu';

    /** Words that make a clause an order or extension for the party (and not, say, a contract term). */
    private const NOTICE_PATTERN = '/notific|cita[çc]|intima|querendo|defesa|manifest|determin|defer|conced|prorrog/iu';

    /** Narrative about a deadline that already ran out ("o prazo ... transcorreu"). */
    private const EXPIRED_PATTERN = '/transcorr|decorrid|esgotad|expirad/iu';

    private const PUBLICATION_PATTERN = '/(?:contad[oa]s?|a\s+contar|computad[oa])[^.;]{0,50}publica|de\s+sua\s+publica/iu';

    private const INSPECTORATE_TABLE_PATTERN = '/Inspetoria\s+Regional\s+de\s+Controle\s+Externo.{0,400}?PROC\s*N[º°o]/isu';

    /** Text right before the Inspetoria clause: "... prestação de contas do período, no prazo de 15 ...". */
    private const INSPECTORATE_CLAUSE_PATTERN = '/contas\s+do\s+per[íi]odo,?\s+no\s*$/iu';

    private const NOTICE_WINDOW = 300;

    private const EXPIRED_WINDOW = 150;

    private const PUBLICATION_WINDOW = 250;

    private const SNIPPET_BEFORE = 200;

    private const SNIPPET_AFTER = 200;

    /** @var array<string, int> */
    private const NUMBERS_IN_WORDS = [
        'um' => 1, 'dois' => 2, 'tres' => 3, 'quatro' => 4, 'cinco' => 5, 'seis' => 6, 'sete' => 7,
        'oito' => 8, 'nove' => 9, 'dez' => 10, 'quinze' => 15, 'vinte' => 20, 'trinta' => 30,
        'quarenta' => 40, 'quarenta e cinco' => 45, 'sessenta' => 60, 'noventa' => 90,
    ];

    /**
     * Deadline of each given process found on the page (processes without one are left out).
     *
     * @param  list<string>  $numeros  processes cited by the occurrence
     * @return array<string, array{dias: int, contagem: ContagemDePrazo, a_partir_da_publicacao: bool, trecho: string}>
     */
    public static function extrair(string $conteudo, array $numeros): array
    {
        if ($conteudo === '' || $numeros === []) {
            return [];
        }

        $alvos = array_flip($numeros);
        $clausulas = self::clausulas($conteudo);
        $temTabela = preg_match(self::INSPECTORATE_TABLE_PATTERN, $conteudo) === 1;
        $prazos = [];

        foreach (EnrichService::processNumberPositions($conteudo) as [$numero, $inicio, $fim]) {
            if (! isset($alvos[$numero]) || isset($prazos[$numero])) {
                continue;
            }

            $prazo = $temTabela && EnrichService::tableRowEnd($conteudo, $fim) !== null
                ? self::inspectorateDeadline($clausulas)
                : self::closestClause($clausulas, $inicio, $fim);

            if ($prazo === null) {
                continue;
            }

            $prazos[$numero] = $prazo;
        }

        return $prazos;
    }

    /**
     * Deadline clauses addressed to a party, in page order.
     *
     * @return list<array{inicio: int, fim: int, inspetoria: bool, dias: int, contagem: ContagemDePrazo, a_partir_da_publicacao: bool, trecho: string}>
     */
    private static function clausulas(string $conteudo): array
    {
        if (! preg_match_all(self::CLAUSE_PATTERN, $conteudo, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $clausulas = [];

        foreach ($matches as $match) {
            [$texto, $inicio] = $match[0];
            $fim = $inicio + strlen($texto);

            if (! self::isNotice($conteudo, $inicio, $fim)) {
                continue;
            }

            $depois = EnrichService::byteSlice($conteudo, $fim, $fim + self::PUBLICATION_WINDOW);

            $clausulas[] = [
                'inicio' => $inicio,
                'fim' => $fim,
                'inspetoria' => preg_match(self::INSPECTORATE_CLAUSE_PATTERN, EnrichService::byteSlice($conteudo, $inicio - 80, $inicio)) === 1,
                'dias' => self::days($match['dias'][0]),
                'contagem' => trim($match['uteis'][0] ?? '') === '' ? ContagemDePrazo::Corridos : ContagemDePrazo::Uteis,
                'a_partir_da_publicacao' => preg_match(self::PUBLICATION_PATTERN, $depois) === 1,
                'trecho' => self::snippet($conteudo, $inicio, $fim),
            ];
        }

        return $clausulas;
    }

    /**
     * An order to a party: notice words around it and no sign the deadline already ran out.
     */
    private static function isNotice(string $conteudo, int $inicio, int $fim): bool
    {
        if (preg_match(self::EXPIRED_PATTERN, EnrichService::byteSlice($conteudo, $fim, $fim + self::EXPIRED_WINDOW)) === 1) {
            return false;
        }

        $entorno = EnrichService::byteSlice($conteudo, $inicio - self::NOTICE_WINDOW, $fim + self::NOTICE_WINDOW);

        return preg_match(self::NOTICE_PATTERN, $entorno) === 1;
    }

    /**
     * Clause closest to the number (either side) within the configured window.
     *
     * @param  list<array<string, mixed>>  $clausulas
     * @return array{dias: int, contagem: ContagemDePrazo, a_partir_da_publicacao: bool, trecho: string}|null
     */
    private static function closestClause(array $clausulas, int $inicio, int $fim): ?array
    {
        $janela = (int) config('tcm.deadline_window_chars');
        $maisProxima = null;
        $menorDistancia = PHP_INT_MAX;

        foreach ($clausulas as $clausula) {
            $distancia = max($clausula['inicio'] - $fim, $inicio - $clausula['fim'], 0);

            if ($distancia > $janela || $distancia >= $menorDistancia) {
                continue;
            }

            $maisProxima = $clausula;
            $menorDistancia = $distancia;
        }

        return $maisProxima === null ? null : self::deadline($maisProxima);
    }

    /**
     * The Inspetoria header clause when it is on the page; otherwise the regimental default,
     * counted from the electronic notice (hence never from the publication).
     *
     * @param  list<array<string, mixed>>  $clausulas
     * @return array{dias: int, contagem: ContagemDePrazo, a_partir_da_publicacao: bool, trecho: string}
     */
    private static function inspectorateDeadline(array $clausulas): array
    {
        foreach ($clausulas as $clausula) {
            if ($clausula['inspetoria']) {
                return self::deadline($clausula);
            }
        }

        $dias = (int) config('tcm.inspectorate_notice_days');

        return [
            'dias' => $dias,
            'contagem' => ContagemDePrazo::Corridos,
            'a_partir_da_publicacao' => false,
            'trecho' => "Edital de notificação do relatório da Inspetoria Regional de Controle Externo: prazo de {$dias} dias, contados a partir da efetivação da notificação eletrônica.",
        ];
    }

    /**
     * @param  array<string, mixed>  $clausula
     * @return array{dias: int, contagem: ContagemDePrazo, a_partir_da_publicacao: bool, trecho: string}
     */
    private static function deadline(array $clausula): array
    {
        return [
            'dias' => $clausula['dias'],
            'contagem' => $clausula['contagem'],
            'a_partir_da_publicacao' => $clausula['a_partir_da_publicacao'],
            'trecho' => $clausula['trecho'],
        ];
    }

    private static function days(string $valor): int
    {
        if (ctype_digit($valor)) {
            return (int) $valor;
        }

        $chave = (string) preg_replace('/\s+/u', ' ', EnrichService::normalize($valor));

        return self::NUMBERS_IN_WORDS[$chave] ?? 0;
    }

    private static function snippet(string $conteudo, int $inicio, int $fim): string
    {
        $trecho = EnrichService::byteSlice($conteudo, $inicio - self::SNIPPET_BEFORE, $fim + self::SNIPPET_AFTER);

        return trim((string) preg_replace('/\s+/u', ' ', $trecho));
    }
}
