<?php

namespace App\Services;

use App\Enums\OccurrenceType;

/**
 * Deterministic summary (no LLM): groups occurrences per term into Markdown + JSON.
 */
class ResumoService
{
    /**
     * @param  array<int, array<string, mixed>>  $occurrences  rows (active terms) in period order
     * @param  array<int, array<string, mixed>>  $terms  term rows ordered by id
     * @param  int  $editionCount  number of editions in the period
     * @param  array<int, array<string, mixed>>  $processes  full process rows (citation order)
     * @param  array<int, list<string>>  $linksPerTerm  termo_id → sorted numbers
     * @return array<string, mixed>
     */
    public static function build(
        array $occurrences,
        array $terms,
        int $editionCount,
        string $periodStart,
        string $periodEnd,
        array $processes,
        array $linksPerTerm,
    ): array {
        $perTerm = [];
        $totalPages = 0;

        foreach ($terms as $term) {
            $occs = array_values(array_filter(
                $occurrences,
                fn (array $o): bool => (int) $o['termo_id'] === (int) $term['id']
            ));

            $pages = array_unique(array_map(fn (array $o): string => "{$o['edicao_id']}:{$o['pagina']}", $occs));
            $datas = array_unique(array_map(fn (array $o): string => (string) $o['data'], $occs));
            rsort($datas, SORT_STRING);
            $itens = array_map(fn (array $o): string => self::line($o), $occs);

            $numbers = [];
            foreach ($occs as $o) {
                foreach ((array) $o['processos'] as $numero) {
                    $numbers[(string) $numero] = true;
                }
            }
            foreach ($linksPerTerm[(int) $term['id']] ?? [] as $numero) {
                $numbers[(string) $numero] = true;
            }
            $numeros = array_keys($numbers);
            sort($numeros, SORT_STRING);

            $totalPages += count($pages);

            if ($occs !== []) {
                $acts = count(array_filter(
                    $occs,
                    fn (array $o): bool => $o['tipo'] === OccurrenceType::Act->value
                ));
                $texto = sprintf(
                    "**%s** — %d página(s), %d ocorrência(s) em %s; %d com ato identificado.\n%s",
                    $term['termo'],
                    count($pages),
                    count($occs),
                    implode(', ', array_map(self::br(...), $datas)),
                    $acts,
                    implode("\n", $itens)
                );
            } else {
                $texto = "**{$term['termo']}** — nada encontrado no período.";
            }

            if ($numeros !== []) {
                $texto .= "\n- Processos: ".implode(', ', array_map(fn (string $n): string => "**{$n}**", $numeros));
            }

            $perTerm[] = [
                'termo' => $term['termo'],
                'tipo' => $term['tipo'],
                'ocorrencias' => count($occs),
                'paginas' => count($pages),
                'datas' => $datas,
                'itens' => $itens,
                'processos' => $numeros,
                'texto' => $texto,
            ];
        }

        $withOccurrence = array_values(array_filter($perTerm, fn (array $t): bool => $t['ocorrencias'] > 0));

        $geralText = [
            '### Resumo — '.self::br($periodStart)
                .($periodEnd !== $periodStart ? ' a '.self::br($periodEnd) : ''),
            '',
            '- **Edições varridas:** '.$editionCount,
            '- **Termos com ocorrência:** '.count($withOccurrence).' de '.count($perTerm),
            '- **Páginas afetadas:** '.$totalPages.' · **ocorrências:** '.count($occurrences),
            '',
        ];

        if ($withOccurrence !== []) {
            foreach ($withOccurrence as $t) {
                $detalhe = $t['itens'][0] ?? '';
                $geralText[] = "- **{$t['termo']}** — {$t['paginas']} página(s), "
                    ."{$t['ocorrencias']} ocorrência(s) · {$detalhe}";
            }
        } else {
            $geralText[] = '- Nenhum termo encontrado no período.';
        }

        $sem = array_values(array_filter($perTerm, fn (array $t): bool => $t['ocorrencias'] === 0));
        $semNomes = array_map(fn (array $t): string => (string) $t['termo'], $sem);

        if ($semNomes !== []) {
            $geralText[] = '';
            $geralText[] = 'Sem ocorrência: '.implode(', ', $semNomes).'.';
        }

        if ($processes !== []) {
            $encontrados = count(array_filter($processes, fn (array $p): bool => ! empty($p['encontrado'])));
            $geralText[] = '';
            $geralText[] = 'Processos citados: '.count($processes)." — {$encontrados} localizados "
                .'na consulta-processual (detalhes abaixo).';
        }

        foreach (self::processSection($processes) as $linha) {
            $geralText[] = $linha;
        }

        return [
            'periodo' => ['inicio' => $periodStart, 'fim' => $periodEnd],
            'geral' => [
                'edicoes' => $editionCount,
                'termos' => count($perTerm),
                'termos_com_ocorrencia' => count($withOccurrence),
                'paginas' => $totalPages,
                'ocorrencias' => count($occurrences),
                'texto' => implode("\n", $geralText),
            ],
            'por_termo' => $perTerm,
            'processos' => $processes,
        ];
    }

    /**
     * One occurrence bullet.
     *
     * @param  array<string, mixed>  $occurrence
     */
    private static function line(array $occurrence): string
    {
        $atos = array_slice((array) $occurrence['atos'], 0, 3);
        $sufixo = $atos !== [] ? ' — '.implode('; ', $atos) : '';
        $rotulo = $occurrence['tipo'] === OccurrenceType::Act->value ? 'ato' : 'menção';

        return '- '.self::br((string) $occurrence['data'])
            ." · p. {$occurrence['pagina']} · **{$rotulo}**{$sufixo}"
            .' · [PDF]('.$occurrence['pdf_url'].')';
    }

    /**
     * Markdown blocks for the processes section (empty when there are none).
     *
     * @param  array<int, array<string, mixed>>  $processes
     * @return list<string>
     */
    private static function processSection(array $processes): array
    {
        if ($processes === []) {
            return [];
        }

        $lines = ['', '### Processos envolvidos ('.count($processes).')', ''];

        foreach ($processes as $proc) {
            if (empty($proc['encontrado'])) {
                $lines[] = "- **{$proc['numero']}** — não localizado na consulta-processual.";

                continue;
            }

            $cabecalho = "- **{$proc['numero']}**";

            if (! empty($proc['data_entrada'])) {
                $cabecalho .= " · entrada {$proc['data_entrada']}";
            }
            if (! empty($proc['situacao'])) {
                $cabecalho .= " · {$proc['situacao']}";
            }
            if (! empty($proc['situacao_data'])) {
                $cabecalho .= " ({$proc['situacao_data']})";
            }

            $consultaUrl = (string) ($proc['consulta_url'] ?? '');

            if ($consultaUrl !== '') {
                $cabecalho .= " · [abrir consulta]({$consultaUrl})";
            }

            $lines[] = $cabecalho;

            if (! empty($proc['autor'])) {
                $lines[] = "  - Autor: {$proc['autor']}";
            }
            if (! empty($proc['complemento'])) {
                $lines[] = "  - {$proc['complemento']}";
            }
        }

        return $lines;
    }

    /**
     * '2026-09-30' -> '30/09/2026'
     */
    private static function br(string $iso): string
    {
        [$ano, $mes, $dia] = explode('-', $iso);

        return "{$dia}/{$mes}/{$ano}";
    }
}
