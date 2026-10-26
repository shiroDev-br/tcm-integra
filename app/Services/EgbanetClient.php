<?php

namespace App\Services;

use App\Exceptions\SourceUnavailable;
use App\Models\Edition;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client over the undocumented EGBANET API (subtheme=tcm):
 * current/recent editions and paginated full-text search (10 hits per page).
 */
class EgbanetClient
{
    /** Number of HTTP attempts made (feeds meta.requests). */
    public int $requests = 0;

    /**
     * Latest published TCM edition (null when there is none).
     */
    public function currentEdition(): ?Edition
    {
        $dados = $this->getJson(
            '/apifront/portal/edicoes/edicoes_from_data.json',
            ['subtheme' => $this->subtheme()]
        );

        if (! empty($dados['erro'])) {
            throw new SourceUnavailable((string) ($dados['msg'] ?? 'erro ao consultar a edição atual'));
        }

        $itens = $dados['itens'] ?? [];

        return isset($itens[0]) ? $this->parseEdition($itens[0]) : null;
    }

    /**
     * ~711 recent editions (3 years), newest first.
     *
     * @return list<Edition>
     */
    public function recentEditions(): array
    {
        $dados = $this->getJson(
            '/apifront/portal/edicoes/ultimas_edicoes.json',
            ['subtheme' => $this->subtheme()]
        );

        if (! empty($dados['erro'])) {
            throw new SourceUnavailable((string) ($dados['msg'] ?? 'erro ao consultar edições recentes'));
        }

        return array_map(
            fn (array $item): Edition => $this->parseEdition($item),
            array_values($dados['itens'] ?? [])
        );
    }

    /**
     * One page of the full-text search (exact phrase) for a single term.
     *
     * @return array<string, mixed>
     */
    public function searchPage(string $term, string $start, string $end, int $page = 0): array
    {
        return $this->getJson($this->searchPath($page, $start, $end), $this->searchParams($term));
    }

    /**
     * Searches several spellings concurrently (rounds per page, limited pool),
     * with progressive retry (0.3 * attempt) on failures.
     *
     * @param  list<string>  $spellings
     * @return array{hits: array<string, list<array<string, mixed>>>, errors: array<string, string>}
     */
    public function searchMany(array $spellings, string $start, string $end): array
    {
        $maxPages = max(1, (int) config('tcm.max_pages'));
        $retries = max(0, (int) config('tcm.http_retries'));
        $concorrencia = max(1, (int) config('tcm.search_concurrency'));

        /** @var array<int, array{grafia: string, pagina: int, hits: list<array<string, mixed>>, total: int|null, done: bool, falhas: int, erro: string|null}> $estados */
        $estados = [];

        foreach ($spellings as $i => $grafia) {
            $estados[$i] = [
                'grafia' => $grafia,
                'pagina' => 0,
                'hits' => [],
                'total' => null,
                'done' => false,
                'falhas' => 0,
                'erro' => null,
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

            $resultados = Http::pool(function (Pool $pool) use ($pendentes, $start, $end): void {
                foreach ($pendentes as $i => $estado) {
                    $pool->as((string) $i)->get(
                        $this->absoluteUrl($this->searchPath($estado['pagina'], $start, $end)),
                        $this->searchParams($estado['grafia'])
                    );
                }
            }, min($concorrencia, count($pendentes)));

            $this->requests += count($pendentes);

            foreach (array_keys($pendentes) as $i) {
                $estado = $estados[$i];
                $path = $this->searchPath($estado['pagina'], $start, $end);
                $resultado = $resultados[$i] ?? null;
                $falha = $this->failureMessage($resultado, $path);

                if ($falha !== null) {
                    $estado['falhas']++;
                    $estado['erro'] = $falha;

                    if ($estado['falhas'] > $retries) {
                        $estado['done'] = true;
                    }

                    $estados[$i] = $estado;

                    continue;
                }

                /** @var Response $response */
                $response = $resultado;
                $dados = json_decode($response->body(), true);

                if (! is_array($dados)) {
                    $estado['falhas']++;
                    $estado['erro'] = "JSON inválido em {$path}";

                    if ($estado['falhas'] > $retries) {
                        $estado['done'] = true;
                    }

                    $estados[$i] = $estado;

                    continue;
                }

                $lote = array_values($dados['hits']['hits'] ?? []);
                $total = $this->totalHits($dados['hits'] ?? []);

                $estado['total'] = $total;
                $estado['hits'] = array_merge($estado['hits'], $lote);
                $estado['pagina']++;

                if ($lote === [] || $estado['pagina'] >= $maxPages) {
                    $estado['done'] = true;
                    $estados[$i] = $estado;

                    continue;
                }

                if (count($estado['hits']) >= $total) {
                    $estado['done'] = true;
                }

                $estados[$i] = $estado;
            }
        }

        $hits = [];
        $errors = [];

        foreach ($estados as $estado) {
            $hits[$estado['grafia']] = $estado['hits'];

            if ($estado['erro'] !== null && $estado['falhas'] > $retries) {
                $errors[$estado['grafia']] = "falha ao acessar {$this->searchPath($estado['pagina'], $start, $end)}: {$estado['erro']}";
            }
        }

        return ['hits' => $hits, 'errors' => $errors];
    }

    /**
     * '30/09/2026' -> '2026-09-30'
     */
    public static function brToIso(string $br): string
    {
        [$dia, $mes, $ano] = explode('/', trim($br));

        return "{$ano}-{$mes}-{$dia}";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function parseEdition(array $item): Edition
    {
        return new Edition([
            'id' => (int) $item['id'],
            'data' => self::brToIso((string) $item['data']),
            'numero' => $item['numero'] ?? null,
            'paginas' => $item['paginas'] ?? null,
            'suplemento' => (int) ($item['suplemento'] ?? 0),
            'tipo_edicao_nome' => $item['tipo_edicao_nome'] ?? null,
        ]);
    }

    /**
     * GET with retry/backoff; any failure becomes SourceUnavailable (502).
     *
     * @param  array<int<0, max>|string, scalar>  $params
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $params = []): array
    {
        $retries = max(0, (int) config('tcm.http_retries'));
        $ultimoErro = null;

        for ($tentativa = 0; $tentativa <= $retries; $tentativa++) {
            try {
                $this->requests++;
                $response = Http::withHeaders($this->headers())
                    ->timeout((float) config('tcm.http_timeout'))
                    ->get($this->absoluteUrl($path), $params);

                if ($response->serverError()) {
                    $ultimoErro = "HTTP {$response->status()} em {$path}";

                    if ($tentativa < $retries) {
                        usleep((int) (0.3 * ($tentativa + 1) * 1_000_000));
                    }

                    continue;
                }

                if ($response->failed()) {
                    $response->throw();
                }

                $dados = json_decode($response->body(), true);

                if (! is_array($dados)) {
                    throw new \RuntimeException("JSON inválido em {$path}");
                }

                return $dados;
            } catch (Throwable $e) {
                $ultimoErro = $e->getMessage();

                if ($tentativa < $retries) {
                    usleep((int) (0.3 * ($tentativa + 1) * 1_000_000));
                }
            }
        }

        throw new SourceUnavailable("falha ao acessar {$path}: {$ultimoErro}");
    }

    /**
     * Failure message for a pooled result (null when the response is usable).
     */
    private function failureMessage(mixed $resultado, string $path): ?string
    {
        if ($resultado instanceof Throwable) {
            return $resultado->getMessage();
        }

        if (! $resultado instanceof Response) {
            return "resposta inválida em {$path}";
        }

        if ($resultado->failed()) {
            return "HTTP {$resultado->status()} em {$path}";
        }

        return null;
    }

    /**
     * ES7+ returns {"value": n} instead of a plain int.
     */
    private function totalHits(mixed $hits): int
    {
        $total = is_array($hits) ? ($hits['total'] ?? 0) : 0;

        if (is_array($total)) {
            $total = $total['value'] ?? 0;
        }

        return (int) $total;
    }

    private function searchPath(int $page, string $start, string $end): string
    {
        return "/busca/busca/buscar/query/{$page}/di:{$start}/df:{$end}/";
    }

    /**
     * @return array<int<0, max>|string, scalar>
     */
    private function searchParams(string $term): array
    {
        return [
            '1' => '1',
            'q' => '"'.$term.'"',
            'subtheme' => $this->subtheme(),
        ];
    }

    private function subtheme(): string
    {
        return (string) config('tcm.subtheme');
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim((string) config('tcm.base_url'), '/').$path;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => (string) config('tcm.user_agent'),
            'Accept' => 'application/json',
        ];
    }
}
