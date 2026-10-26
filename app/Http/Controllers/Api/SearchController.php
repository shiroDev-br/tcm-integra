<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SourceUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\PeriodRequest;
use App\Http\Resources\SearchResultResource;
use App\Models\Edition;
use App\Services\Clock;
use App\Services\EgbanetClient;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
        private readonly EgbanetClient $egba,
    ) {}

    /**
     * Latest edition published at the source (real time).
     */
    public function currentEdition(): JsonResponse
    {
        $edicao = $this->egba->currentEdition();

        if ($edicao === null) {
            throw new SourceUnavailable('nenhuma edição publicada');
        }

        return response()->json(SearchService::editionPayload($edicao));
    }

    /**
     * Editions of the last `dias` days (real time at the source).
     */
    public function editions(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'dias' => ['sometimes', 'integer', 'min:1', 'max:1100'],
        ]);
        $dias = (int) ($validado['dias'] ?? 30);
        $hoje = Clock::today();
        $inicio = (new \DateTimeImmutable($hoje))->modify('-'.($dias - 1).' days')->format('Y-m-d');

        $edicoes = array_values(array_filter(
            $this->egba->recentEditions(),
            fn (Edition $e): bool => $inicio <= $e->data && $e->data <= $hoje
        ));

        return response()->json(array_map(
            fn (Edition $e): array => SearchService::editionPayload($e),
            $edicoes
        ));
    }

    /**
     * Collects the period and returns occurrences + summary (real time).
     */
    public function search(PeriodRequest $request): JsonResponse
    {
        return (new SearchResultResource($this->search->execute($request->validated())))
            ->response();
    }

    /**
     * Occurrences already collected for the period (local cache).
     */
    public function occurrences(PeriodRequest $request): JsonResponse
    {
        $payload = $this->search->cachedPayload($request->validated());

        return response()->json($payload['ocorrencias']);
    }

    /**
     * Deterministic summary of the period (local cache).
     */
    public function summary(PeriodRequest $request): JsonResponse
    {
        $payload = $this->search->cachedPayload($request->validated());

        return response()->json($payload['resumo']);
    }

    /**
     * Processes cited in the period, already enriched (local cache).
     */
    public function processes(PeriodRequest $request): JsonResponse
    {
        $payload = $this->search->cachedPayload($request->validated());

        return response()->json($payload['processos']);
    }
}
