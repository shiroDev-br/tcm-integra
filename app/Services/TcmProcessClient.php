<?php

namespace App\Services;

use App\Exceptions\SourceUnavailable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * TCM-BA process consultation (consulta-processual) — HTTP POST + HTML scraping.
 *
 * Contract verified experimentally (30/09/2026): POST form-urlencoded
 * proc=<numero>&consulta=ok&B1=Consultar; missing process returns an
 * alert-warning "Não foi possivel localizar o processo".
 */
class TcmProcessClient
{
    /** Number of HTTP attempts made (feeds meta.requests_tcm). */
    public int $requests = 0;

    private const LABEL_PATTERN = '#<label[^>]*>\s*([^<]+?)\s*:?\s*</label>\s*<span[^>]*>(.*?)</span>#is';

    private const TABLE_PATTERN = '#<table[^>]*id="tabelaResultado".*?</table>#is';

    private const ABSENT = 'Não foi possivel localizar o processo';

    private const MODAL_MARKER = '<!-- MODAL DE ANDAMENTO -->';

    /**
     * Looks up several processes concurrently (rounds, limited pool) with
     * progressive retry; only HTTP-level failures are retried (POST is idempotent).
     *
     * @param  list<string>  $numbers
     * @return array{resultados: array<string, array<string, mixed>>, erros: array<string, string>}
     */
    public function lookupMany(array $numbers): array
    {
        $retries = max(0, (int) config('tcm.http_retries'));
        $concorrencia = max(1, (int) config('tcm.tcm_concurrency'));

        /** @var array<int, array{numero: string, done: bool, falhas: int, erro: string|null, fatal: bool, resultado?: array<string, mixed>}> $estados */
        $estados = [];

        foreach ($numbers as $i => $numero) {
            $estados[$i] = [
                'numero' => $numero,
                'done' => false,
                'falhas' => 0,
                'erro' => null,
                'fatal' => false,
            ];
        }

        while (true) {
            $pendentes = array_filter($estados, fn (array $e): bool => ! $e['done']);

            if ($pendentes === []) {
                break;
            }

            $maxFalhas = array_reduce(
                $pendentes,
                fn (int $max, array $e): int => max($max, $e['falhas']),
                0
            );

            if ($maxFalhas > 0) {
                usleep((int) (0.3 * $maxFalhas * 1_000_000));
            }

            $resultados = Http::pool(function (Pool $pool) use ($pendentes): void {
                foreach ($pendentes as $i => $estado) {
                    $pending = $pool->as((string) $i)
                        ->withHeaders($this->headers())
                        ->timeout((float) config('tcm.process_lookup_timeout', 10.0))
                        ->asForm();

                    $proxy = (string) config('tcm.process_lookup_proxy');

                    if ($proxy !== '') {
                        $pending->withOptions(['proxy' => $proxy]);
                    }

                    $pending->post($this->lookupUrl(), $this->form($estado['numero']));
                }
            }, min($concorrencia, count($pendentes)));

            $this->requests += count($pendentes);

            foreach (array_keys($pendentes) as $i) {
                $estado = $estados[$i];
                $numero = $estado['numero'];
                $resultado = $resultados[$i] ?? null;
                $falha = $this->failureMessage($resultado);

                if ($falha !== null) {
                    $estado['falhas']++;
                    $estado['erro'] = $falha;

                    if ($estado['falhas'] > $retries) {
                        $estado['done'] = true;
                    }

                    $estados[$i] = $estado;

                    continue;
                }

                /** @var Response $resultado */
                try {
                    $estado['resultado'] = self::parseLookup(
                        $resultado->body(),
                        $numero,
                        $this->lookupUrl()
                    );
                    $estado['done'] = true;
                } catch (SourceUnavailable $e) {
                    // unexpected markup: fatal, never retried (matches the source)
                    $estado['erro'] = $e->getMessage();
                    $estado['fatal'] = true;
                    $estado['done'] = true;
                }

                $estados[$i] = $estado;
            }
        }

        $porNumero = [];
        $erros = [];

        foreach ($estados as $estado) {
            if (isset($estado['resultado'])) {
                $porNumero[$estado['numero']] = $estado['resultado'];

                continue;
            }

            $numero = $estado['numero'];
            $erros[$numero] = $estado['fatal'] === true
                ? (string) $estado['erro']
                : "falha ao consultar o processo {$numero}: ".((string) ($estado['erro'] ?? 'erro desconhecido'));
        }

        return ['resultados' => $porNumero, 'erros' => $erros];
    }

    /**
     * Single process lookup (same retry semantics as the many-variant).
     *
     * @return array<string, mixed>
     *
     * @throws SourceUnavailable
     */
    public function lookup(string $numero): array
    {
        $lote = $this->lookupMany([$numero]);

        if (isset($lote['resultados'][$numero])) {
            return $lote['resultados'][$numero];
        }

        throw new SourceUnavailable($lote['erros'][$numero]);
    }

    /**
     * Scrapes the consultation result page.
     *
     * @return array<string, mixed>
     *
     * @throws SourceUnavailable on unexpected markup
     */
    public static function parseLookup(string $html, string $numero, string $url): array
    {
        $consultado = Clock::nowInTimezoneIso();

        if (str_contains($html, self::ABSENT)) {
            return [
                'numero' => $numero,
                'encontrado' => false,
                'data_entrada' => null,
                'autor' => null,
                'complemento' => null,
                'municipio' => null,
                'situacao' => null,
                'situacao_data' => null,
                'meio' => null,
                'andamento' => [],
                'consulta_url' => $url,
                'consultado_em' => $consultado,
            ];
        }

        if (! str_contains($html, 'Dados do Processo:')) {
            throw new SourceUnavailable("markup inesperado na consulta do processo {$numero}");
        }

        // only the main block: the modal repeats the same labels with other values
        $principal = explode(self::MODAL_MARKER, $html)[0];
        $campos = self::extractFields($principal);
        $andamento = self::extractRows($html);

        return [
            'numero' => $numero,
            'encontrado' => true,
            'data_entrada' => $campos['Data de Entrada'] ?? null,
            'autor' => ($campos['Interessado/Autor'] ?? '') !== '' ? $campos['Interessado/Autor'] : null,
            'complemento' => ($campos['Complemento'] ?? '') !== '' ? $campos['Complemento'] : null,
            // only some processes report it (prestação de contas ones do not)
            'municipio' => ($campos['Município'] ?? '') !== '' ? $campos['Município'] : null,
            'situacao' => $andamento[0]['situacao'] ?? null,
            'situacao_data' => $campos['Data'] ?? null,
            'meio' => ($campos['Meio'] ?? '') !== '' ? $campos['Meio'] : null,
            'andamento' => $andamento,
            'consulta_url' => $url,
            'consultado_em' => $consultado,
        ];
    }

    /**
     * <label>Label:</label><span>Value</span> pairs — first occurrence wins.
     *
     * @return array<string, string>
     */
    private static function extractFields(string $html): array
    {
        $campos = [];

        if (! preg_match_all(self::LABEL_PATTERN, $html, $matches, PREG_SET_ORDER)) {
            return $campos;
        }

        foreach ($matches as [$complete, $rotulo, $valor]) {
            $chave = trim(rtrim(self::text($rotulo), ':'));

            if ($chave !== '' && ! array_key_exists($chave, $campos)) {
                $campos[$chave] = self::text($valor);
            }
        }

        return $campos;
    }

    /**
     * Table #tabelaResultado: (Unidade, Data, Situação, Observação), newest first.
     *
     * @return list<array{unidade: string, data: string, situacao: string, observacao: string}>
     */
    private static function extractRows(string $html): array
    {
        if (! preg_match(self::TABLE_PATTERN, $html, $tabela)) {
            return [];
        }

        $linhas = [];

        if (! preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $tabela[0], $trs)) {
            return [];
        }

        foreach ($trs[1] as $bruto) {
            preg_match_all('#<t[dh][^>]*>(.*?)</t[dh]>#is', $bruto, $tds);
            $celulas = array_map(
                fn (string $celula): string => self::text($celula),
                $tds[1]
            );

            if (count($celulas) < 4 || strtolower($celulas[0]) === 'unidade') {
                continue; // header
            }

            $linhas[] = [
                'unidade' => $celulas[0],
                'data' => $celulas[1],
                'situacao' => $celulas[2],
                'observacao' => $celulas[3],
            ];
        }

        return $linhas;
    }

    /**
     * Removes tags and collapses whitespace.
     */
    private static function text(string $html): string
    {
        $texto = html_entity_decode(
            (string) preg_replace('#<[^>]+>#u', ' ', $html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * @return array<string, string>
     */
    private function form(string $numero): array
    {
        return [
            'proc' => $numero,
            'consulta' => 'ok',
            'B1' => 'Consultar',
        ];
    }

    private function lookupUrl(): string
    {
        return (string) config('tcm.process_lookup_url');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['User-Agent' => (string) config('tcm.user_agent')];
    }

    private function failureMessage(mixed $resultado): ?string
    {
        if ($resultado instanceof Throwable) {
            return $resultado->getMessage();
        }

        if (! $resultado instanceof Response) {
            return 'resposta inválida';
        }

        if ($resultado->serverError()) {
            return "HTTP {$resultado->status()} na consulta";
        }

        if ($resultado->failed()) {
            return "HTTP {$resultado->status()} na consulta";
        }

        return null;
    }
}
