<?php

namespace App\Services;

use App\Enums\OccurrenceType;
use App\Enums\SearchStatus;
use App\Enums\TermType;
use App\Enums\VereditoDoPortal;
use App\Exceptions\SourceUnavailable;
use App\Models\Edition;
use App\Models\Occurrence;
use App\Models\Prazo;
use App\Models\Process;
use App\Models\SearchRun;
use App\Models\Term;
use App\Models\TermProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Collection service: detects editions, searches every active term (concurrently)
 * and persists occurrences + process links. Mirrors the original ColetaService.
 */
class SearchService
{
    public function __construct(
        private readonly EgbanetClient $egba,
        private readonly TcmProcessClient $tcm,
    ) {}

    /**
     * Collects the whole period: 1 editions request + N term searches.
     *
     * @param  array<string, mixed>  $input  validated PeriodRequest data
     * @return array<string, mixed> BuscaOut payload
     *
     * @throws SourceUnavailable
     */
    public function execute(array $input): array
    {
        // A coleta é síncrona e pode consultar dezenas de processos no TCM;
        // o limite padrão do PHP (30s) estoura antes de terminar.
        set_time_limit((int) config('tcm.search_time_limit', 180));

        $t0 = microtime(true);
        $reqsIni = $this->egba->requests;
        $tcmIni = $this->tcm->requests;
        $periodo = PeriodRange::fromInput($input);
        $avisos = [];
        $termos = $this->activeTerms();

        try {
            $edicoes = $this->editionsForPeriod($periodo, $avisos);
        } catch (SourceUnavailable $e) {
            $this->registerRun($periodo, count($termos), $this->egba->requests - $reqsIni, $t0, SearchStatus::Error, $e->getMessage());

            throw $e;
        }

        $conhecidas = $this->upsertEditions($edicoes);

        $this->searchAndPersist($periodo, $termos, $conhecidas, $avisos);
        Process::atualizarPrazoVencimento($this->numbersInPeriod($periodo));

        $duracao = (int) ((microtime(true) - $t0) * 1000);
        $requests = $this->egba->requests - $reqsIni;
        $status = $avisos === [] ? SearchStatus::Ok : SearchStatus::Partial;

        $this->registerRun($periodo, count($termos), $requests, $t0, $status, $avisos === [] ? null : implode('; ', $avisos));

        $ocorrencias = $this->occurrenceRows($periodo);
        $processos = $this->processRows($periodo);
        $vinculos = $this->linksByTerm($periodo);

        $meta = [
            'duracao_ms' => $duracao,
            'requests' => $requests,
            'requests_tcm' => $this->tcm->requests - $tcmIni,
            'avisos' => $avisos,
            'cache' => false,
        ];

        return $this->payload($periodo, $termos, $edicoes, $ocorrencias, $processos, $vinculos, $meta);
    }

    /**
     * Payload of an already collected period (no source requests).
     *
     * @param  array<string, mixed>  $input  validated PeriodRequest data
     * @return array<string, mixed> BuscaOut payload
     */
    public function cachedPayload(array $input): array
    {
        $periodo = PeriodRange::fromInput($input);
        $termos = $this->activeTerms();
        $edicoes = array_values(
            Edition::query()
                ->whereBetween('data', [$periodo->start, $periodo->end])
                ->orderByDesc('data')
                ->get()
                ->all()
        );

        return $this->payload(
            $periodo,
            $termos,
            $edicoes,
            $this->occurrenceRows($periodo),
            $this->processRows($periodo),
            $this->linksByTerm($periodo),
            ['duracao_ms' => 0, 'requests' => 0, 'requests_tcm' => 0, 'avisos' => [], 'cache' => true],
        );
    }

    // ------------------------------------------------------------------ internos

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeTerms(): array
    {
        return Term::query()
            ->ativo()
            ->orderBy('id')
            ->get()
            ->map(fn (Term $t): array => $t->toArray())
            ->all();
    }

    /**
     * Editions of the period from the source (newest first).
     *
     * @param  list<string>  $avisos
     * @return list<Edition>
     */
    private function editionsForPeriod(PeriodRange $periodo, array &$avisos): array
    {
        $edicoes = array_values(array_filter(
            $this->egba->recentEditions(),
            fn (Edition $e): bool => $periodo->contains($e->data)
        ));

        if ($edicoes === []) {
            $atual = $this->egba->currentEdition();

            if ($atual !== null && $periodo->contains($atual->data)) {
                $edicoes = [$atual];
            } else {
                $avisos[] = "nenhuma edição publicada entre {$periodo->start} e {$periodo->end}";
            }
        }

        usort($edicoes, fn (Edition $a, Edition $b): int => strcmp($b->data, $a->data));

        return $edicoes;
    }

    /**
     * Upserts the editions (updates numero/paginas on conflict) and returns their ids.
     *
     * @param  list<Edition>  $edicoes
     * @return array<int, true>
     */
    private function upsertEditions(array $edicoes): array
    {
        if ($edicoes !== []) {
            DB::table('edicoes')->upsert(
                array_map(fn (Edition $e): array => [
                    'id' => $e->id,
                    'data' => $e->data,
                    'numero' => $e->numero,
                    'paginas' => $e->paginas,
                    'suplemento' => $e->suplemento,
                    'tipo_edicao_nome' => $e->tipo_edicao_nome,
                ], $edicoes),
                ['id'],
                ['numero', 'paginas'],
            );
        }

        return array_fill_keys(array_map(fn (Edition $e): int => $e->id, $edicoes), true);
    }

    /**
     * Searches every term, confirms which cited processes really belong to it and persists
     * only confirmed data: occurrences (with their confirmed processes), term↔process links,
     * the looked-up processes and their deadlines.
     *
     * @param  array<int, array<string, mixed>>  $termos
     * @param  array<int, true>  $conhecidas
     * @param  list<string>  $avisos
     */
    private function searchAndPersist(PeriodRange $periodo, array $termos, array $conhecidas, array &$avisos): void
    {
        $achados = $this->searchHits($periodo, $termos, $avisos);
        $processos = $this->lookupCandidates($achados, $avisos);
        $confirmados = [];

        foreach ($achados as $achado) {
            $enriquecido = $achado['enriquecido'];
            $enriquecido['processos'] = self::confirmedNumbers($enriquecido['evidencias'], $processos, $achado['termo']);
            $confirmados += array_fill_keys($enriquecido['processos'], true);

            $this->ensureEdition($achado['hit'], $conhecidas);
            $this->insertOccurrence($enriquecido, (int) $achado['termo']['id'], $periodo);
            $this->insertDeadlines($achado['hit'], $enriquecido);
        }

        Process::registrarConsultas(
            $processos->filter(fn (Process $processo): bool => ! $processo->exists && isset($confirmados[$processo->numero]))
        );
    }

    /**
     * Hits of every spelling (term + variations), searched concurrently and enriched.
     * Pages where the spelling is not actually written are discarded.
     *
     * @param  array<int, array<string, mixed>>  $termos
     * @param  list<string>  $avisos
     * @return list<array{termo: array<string, mixed>, hit: array<string, mixed>, enriquecido: array{edicao_id: int, pagina: int, trecho: string, contexto: string, atos: list<string>, processos: list<string>, evidencias: array<string, Evidencia>, tipo: OccurrenceType, ocorrencias_na_pagina: int, pdf_url: string, hit_id: string}}>
     */
    private function searchHits(PeriodRange $periodo, array $termos, array &$avisos): array
    {
        /** @var list<array{termo: array<string, mixed>, grafia: string}> $grafias */
        $grafias = [];

        foreach ($termos as $termo) {
            foreach (self::spellings($termo) as $grafia) {
                $grafias[] = ['termo' => $termo, 'grafia' => $grafia];
            }
        }

        if ($grafias === []) {
            return [];
        }

        $resultado = $this->egba->searchMany(
            array_column($grafias, 'grafia'),
            $periodo->start,
            $periodo->end
        );

        $achados = [];

        foreach ($grafias as $item) {
            $grafia = $item['grafia'];

            if (isset($resultado['errors'][$grafia])) {
                $avisos[] = "falha ao buscar '{$grafia}': {$resultado['errors'][$grafia]}";

                continue;
            }

            foreach ($resultado['hits'][$grafia] ?? [] as $hit) {
                $enriquecido = EnrichService::enrich($hit, $grafia);

                if ($enriquecido['edicao_id'] === 0 || $enriquecido['ocorrencias_na_pagina'] === 0) {
                    continue;
                }

                $achados[] = ['termo' => $item['termo'], 'hit' => $hit, 'enriquecido' => $enriquecido];
            }
        }

        return $achados;
    }

    /**
     * Term + variations, the spellings searched and matched against the portal data.
     *
     * @param  array<string, mixed>  $termo
     * @return list<string>
     */
    private static function spellings(array $termo): array
    {
        return [
            (string) $termo['termo'],
            ...array_map(strval(...), array_values((array) ($termo['variacoes'] ?? []))),
        ];
    }

    /**
     * Portal data of every candidate process: cached rows plus fresh (unsaved) lookups.
     * Lookup failures become warnings; those candidates are judged without portal data.
     *
     * @param  list<array{termo: array<string, mixed>, hit: array<string, mixed>, enriquecido: array{evidencias: array<string, Evidencia>}}>  $achados
     * @param  list<string>  $avisos
     * @return Collection<string, Process>
     */
    private function lookupCandidates(array $achados, array &$avisos): Collection
    {
        $candidatos = [];

        foreach ($achados as $achado) {
            $candidatos += $achado['enriquecido']['evidencias'];
        }

        $numeros = array_map(strval(...), array_keys($candidatos));

        /** @var Collection<string, Process> $processos */
        $processos = Process::query()->whereIn('numero', $numeros)->get()->keyBy('numero')->toBase();
        $pendentes = array_values(array_diff($numeros, $processos->keys()->all()));

        if ($pendentes === []) {
            return $processos;
        }

        $resultado = $this->tcm->lookupMany($pendentes);

        foreach ($resultado['erros'] as $numero => $erro) {
            $avisos[] = "falha ao consultar o processo '{$numero}': {$erro}";
        }

        foreach ($resultado['resultados'] as $numero => $consulta) {
            $processos->put((string) $numero, Process::daConsulta($consulta));
        }

        return $processos;
    }

    /**
     * Candidates whose page evidence stands against the portal verdict for the term.
     *
     * @param  array<string, Evidencia>  $evidencias
     * @param  Collection<string, Process>  $processos
     * @param  array<string, mixed>  $termo
     * @return list<string>
     */
    private static function confirmedNumbers(array $evidencias, Collection $processos, array $termo): array
    {
        $tipo = TermType::from((string) $termo['tipo']);
        $grafias = self::spellings($termo);
        $confirmados = [];

        foreach ($evidencias as $numero => $evidencia) {
            $veredito = $processos->get((string) $numero)?->vereditoPara($tipo, $grafias, $evidencia->trecho)
                ?? VereditoDoPortal::Neutro;

            if (! $evidencia->forca->aceita($veredito)) {
                continue;
            }

            $confirmados[] = (string) $numero;
        }

        return $confirmados;
    }

    /**
     * Guarantees the hit's edition exists (FK) even if the source listing lagged.
     *
     * @param  array<string, mixed>  $hit
     * @param  array<int, true>  $conhecidas
     */
    private function ensureEdition(array $hit, array &$conhecidas): void
    {
        $fonte = self::source($hit);
        $edicaoId = (int) ($fonte['diario_id'] ?? 0);

        if ($edicaoId === 0 || isset($conhecidas[$edicaoId])) {
            return;
        }

        DB::table('edicoes')->upsert(
            [[
                'id' => $edicaoId,
                'data' => (string) ($fonte['data'] ?? ''),
                'numero' => null,
                'paginas' => $fonte['paginas'] ?? null,
                'suplemento' => 0,
                'tipo_edicao_nome' => null,
            ]],
            ['id'],
            ['numero', 'paginas'],
        );

        $conhecidas[$edicaoId] = true;
    }

    /**
     * Idempotent occurrence insert (PK includes the period) + term↔process links.
     *
     * @param  array<string, mixed>  $enriquecido
     */
    private function insertOccurrence(array $enriquecido, int $termoId, PeriodRange $periodo): void
    {
        DB::table('ocorrencias')->insertOrIgnore([
            'edicao_id' => $enriquecido['edicao_id'],
            'pagina' => $enriquecido['pagina'],
            'termo_id' => $termoId,
            'trecho' => $enriquecido['trecho'],
            'contexto' => $enriquecido['contexto'],
            'atos' => json_encode($enriquecido['atos'], JSON_UNESCAPED_UNICODE),
            'processos' => json_encode($enriquecido['processos'], JSON_UNESCAPED_UNICODE),
            'tipo' => $enriquecido['tipo'] instanceof OccurrenceType
                ? $enriquecido['tipo']->value
                : (string) $enriquecido['tipo'],
            'ocorrencias_na_pagina' => $enriquecido['ocorrencias_na_pagina'],
            'pdf_url' => $enriquecido['pdf_url'],
            'periodo_inicio' => $periodo->start,
            'periodo_fim' => $periodo->end,
            'created_at' => Clock::nowUtcIso(),
        ]);

        foreach ((array) $enriquecido['processos'] as $numero) {
            DB::table('termo_processos')->insertOrIgnore([
                'termo_id' => $termoId,
                'processo_numero' => (string) $numero,
                'periodo_inicio' => $periodo->start,
                'periodo_fim' => $periodo->end,
                'created_at' => Clock::nowUtcIso(),
            ]);
        }
    }

    /**
     * Deadlines of the processes cited by the occurrence, read from the whole page.
     *
     * @param  array<string, mixed>  $hit
     * @param  array{edicao_id: int, pagina: int, processos: list<string>}  $enriquecido
     */
    private function insertDeadlines(array $hit, array $enriquecido): void
    {
        $fonte = self::source($hit);

        Prazo::registrar(
            ExtratorDePrazo::extrair((string) ($fonte['conteudo'] ?? ''), $enriquecido['processos']),
            $enriquecido['edicao_id'],
            $enriquecido['pagina'],
            (string) ($fonte['data'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    private static function source(array $hit): array
    {
        return is_array($hit['_source'] ?? null) ? $hit['_source'] : [];
    }

    /**
     * Numbers cited in the period: occurrences + term↔process links (active terms).
     *
     * @return list<string>
     */
    private function numbersInPeriod(PeriodRange $periodo): array
    {
        $numeros = [];

        $ocorrencias = Occurrence::query()
            ->join('termos', 'termos.id', '=', 'ocorrencias.termo_id')
            ->where('ocorrencias.periodo_inicio', $periodo->start)
            ->where('ocorrencias.periodo_fim', $periodo->end)
            ->where('termos.ativo', true)
            ->pluck('ocorrencias.processos');

        foreach ($ocorrencias as $processos) {
            $lista = is_array($processos)
                ? $processos
                : (array) json_decode((string) $processos, true);

            foreach ($lista as $numero) {
                $numeros[(string) $numero] = true;
            }
        }

        $vinculos = TermProcess::query()
            ->join('termos', 'termos.id', '=', 'termo_processos.termo_id')
            ->where('termo_processos.periodo_inicio', $periodo->start)
            ->where('termo_processos.periodo_fim', $periodo->end)
            ->where('termos.ativo', true)
            ->pluck('termo_processos.processo_numero');

        foreach ($vinculos as $numero) {
            $numeros[(string) $numero] = true;
        }

        $chaves = array_keys($numeros);
        sort($chaves, SORT_STRING);

        return $chaves;
    }

    /**
     * Occurrence rows (active terms) in payload order.
     *
     * @return list<array<string, mixed>>
     */
    private function occurrenceRows(PeriodRange $periodo): array
    {
        $linhas = Occurrence::query()
            ->join('termos', 'termos.id', '=', 'ocorrencias.termo_id')
            ->join('edicoes', 'edicoes.id', '=', 'ocorrencias.edicao_id')
            ->where('ocorrencias.periodo_inicio', $periodo->start)
            ->where('ocorrencias.periodo_fim', $periodo->end)
            ->where('termos.ativo', true)
            ->orderBy('termos.termo')
            ->orderByDesc('edicoes.data')
            ->orderBy('ocorrencias.pagina')
            ->select([
                'ocorrencias.*',
                'termos.termo',
                'termos.tipo AS tipo_termo',
                'edicoes.data AS edicao_data',
            ])
            ->get();

        $linhas = $linhas->map(fn (Occurrence $o): array => [
            'termo' => $o->getAttribute('termo'),
            'termo_id' => $o->termo_id,
            'tipo_termo' => $o->getAttribute('tipo_termo'),
            'edicao_id' => $o->edicao_id,
            'data' => $o->getAttribute('edicao_data'),
            'pagina' => $o->pagina,
            'tipo' => $o->tipo->value,
            'trecho' => $o->trecho,
            'contexto' => $o->contexto,
            'atos' => (array) $o->atos,
            'ocorrencias_na_pagina' => $o->ocorrencias_na_pagina,
            'pdf_url' => $o->pdf_url,
            'processos' => (array) $o->processos,
        ])->all();

        return array_values($linhas);
    }

    /**
     * Cached processes cited in the period (sorted numbers, consulta_url attached).
     *
     * @return list<array<string, mixed>>
     */
    private function processRows(PeriodRange $periodo): array
    {
        $url = (string) config('tcm.process_lookup_url');
        $rows = [];

        $numeros = $this->numbersInPeriod($periodo);
        $cache = Process::query()->whereIn('numero', $numeros)->get()->keyBy('numero');

        foreach ($numeros as $numero) {
            $p = $cache->get($numero);

            if ($p === null) {
                continue;
            }

            $rows[] = [
                'numero' => $p->numero,
                'encontrado' => (bool) $p->encontrado,
                'data_entrada' => $p->data_entrada,
                'autor' => $p->autor,
                'complemento' => $p->complemento,
                'situacao' => $p->situacao,
                'situacao_data' => $p->situacao_data,
                'meio' => $p->meio,
                'andamento' => (array) $p->andamento,
                'consulta_url' => $url,
                'consultado_em' => $p->consultado_em,
            ];
        }

        return $rows;
    }

    /**
     * termo_id → sorted numbers linked in the period.
     *
     * @return array<int, list<string>>
     */
    private function linksByTerm(PeriodRange $periodo): array
    {
        $linhas = TermProcess::query()
            ->where('periodo_inicio', $periodo->start)
            ->where('periodo_fim', $periodo->end)
            ->get(['termo_id', 'processo_numero']);

        $agrupados = [];

        foreach ($linhas as $linha) {
            $agrupados[(int) $linha->termo_id][(string) $linha->processo_numero] = true;
        }

        $saida = [];

        foreach ($agrupados as $termoId => $numeros) {
            $chaves = array_keys($numeros);
            sort($chaves, SORT_STRING);
            $saida[$termoId] = $chaves;
        }

        return $saida;
    }

    /**
     * BuscaOut payload (search result with deterministic summary).
     *
     * @param  array<int, array<string, mixed>>  $termos
     * @param  list<Edition>  $edicoes
     * @param  list<array<string, mixed>>  $ocorrencias
     * @param  list<array<string, mixed>>  $processos
     * @param  array<int, list<string>>  $vinculos
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function payload(
        PeriodRange $periodo,
        array $termos,
        array $edicoes,
        array $ocorrencias,
        array $processos,
        array $vinculos,
        array $meta,
    ): array {
        $resumo = ResumoService::build(
            $ocorrencias,
            $termos,
            count($edicoes),
            $periodo->start,
            $periodo->end,
            $processos,
            $vinculos,
        );

        return [
            'periodo' => $periodo->toArray(),
            'edicoes' => array_map(fn (Edition $e): array => self::editionPayload($e), $edicoes),
            'ocorrencias' => $ocorrencias,
            'processos' => $processos,
            'resumo' => $resumo,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function editionPayload(Edition $e): array
    {
        return [
            'id' => (int) $e->id,
            'data' => $e->data,
            'numero' => $e->numero,
            'paginas' => $e->paginas,
            'suplemento' => $e->suplemento ? 1 : 0,
            'tipo_edicao_nome' => $e->tipo_edicao_nome,
        ];
    }

    /**
     * Records the run in `buscas` (audit trail of each collection).
     */
    private function registerRun(
        PeriodRange $periodo,
        int $termos,
        int $requests,
        float $t0,
        SearchStatus $status,
        ?string $erro,
    ): void {
        SearchRun::query()->create([
            'periodo_inicio' => $periodo->start,
            'periodo_fim' => $periodo->end,
            'termos' => $termos,
            'requests' => $requests,
            'duracao_ms' => (int) ((microtime(true) - $t0) * 1000),
            'status' => $status,
            'erro' => $erro,
            'created_at' => Clock::nowUtcIso(),
        ]);
    }
}
