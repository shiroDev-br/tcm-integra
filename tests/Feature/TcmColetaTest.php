<?php

use App\Models\Edition;
use App\Models\Prazo;
use App\Models\Process;
use App\Models\Term;
use App\Services\SearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedTcmBase();
    onlyMaiquinique();
});

test('coleta do dia', function () {
    $mock = fakeTcmHttp();
    $payload = app(SearchService::class)->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    expect(array_column($payload['edicoes'], 'id'))->toBe([22622]);
    expect($payload['edicoes'][0]['numero'])->toBe(2899);
    expect($payload['ocorrencias'])->toHaveCount(1);

    $occ = $payload['ocorrencias'][0];
    expect($occ['termo'])->toBe('Maiquinique');
    expect($occ['pagina'])->toBe(2);
    expect($occ['data'])->toBe(DI_TESTE);
    expect($occ['pdf_url'])->toEndWith('/22622/2');
    // a página cita vários municípios; só o processo das contas de Maiquinique é dela
    expect($occ['processos'])->toBe(['10030e25']);

    // 1 (edicoes) + 1 (busca do termo ativo)
    expect($payload['meta']['requests'])->toBe(2);
    // enriquecimento dos processos citados (1 por processo nao cacheado)
    expect($payload['meta']['requests_tcm'])->toBe(count($occ['processos']));
    expect($mock->tcm)->toHaveCount(count($occ['processos']));
    expect($payload['meta']['avisos'])->toBe([]);

    $resumo = $payload['resumo'];
    expect($resumo['geral']['termos_com_ocorrencia'])->toBe(1);
    expect($resumo['geral']['edicoes'])->toBe(1);
    expect($resumo['geral']['texto'])->toContain('**Maiquinique**');
    // a coleta resume so o que foi buscado (termos ativos)
    expect(array_column($resumo['por_termo'], 'termo'))->toBe(['Maiquinique']);

    // processos enriquecidos na coleta (mock devolve a fixture encontrada)
    $numeros = array_column($payload['processos'], 'numero');
    sort($numeros, SORT_STRING);
    $esperado = array_values($occ['processos']);
    sort($esperado, SORT_STRING);
    expect($numeros)->toBe($esperado);

    foreach ($payload['processos'] as $processo) {
        expect($processo['encontrado'])->toBeTrue();
    }

    $proc = collect($payload['processos'])->firstWhere('numero', '10030e25');
    expect($proc['andamento'])->not->toBeEmpty();
    expect($proc['situacao_data'])->toBe('30/09/2026');
    expect($resumo['geral']['texto'])->toContain('### Processos envolvidos');
});

test('coleta e idempotente', function () {
    fakeTcmHttp();
    $svc = app(SearchService::class);

    $svc->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);
    $vinculos = DB::table('termo_processos')->count();
    $payload2 = $svc->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    expect($payload2['ocorrencias'])->toHaveCount(1);
    expect(DB::table('ocorrencias')->count())->toBe(1);
    expect(DB::table('buscas')->count())->toBe(2);
    // os vinculos termo<->processo tambem sao idempotentes por periodo
    expect($vinculos)->toBeGreaterThan(0);
    expect(DB::table('termo_processos')->count())->toBe($vinculos);
    // segunda coleta reaproveita o cache de processos (0 consultas novas)
    expect($payload2['meta']['requests_tcm'])->toBe(0);
});

test('processos ficam vinculados ao termo do periodo', function () {
    fakeTcmHttp();
    $payload = app(SearchService::class)->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    $termoId = (int) Term::query()->where('termo', 'Maiquinique')->value('id');
    $linhas = DB::table('termo_processos')->get();

    expect(collect($linhas)->pluck('termo_id')->unique()->all())->toBe([$termoId]);
    $vinculados = collect($linhas)->pluck('processo_numero')->all();
    sort($vinculados, SORT_STRING);
    $esperado = array_values($payload['ocorrencias'][0]['processos']);
    sort($esperado, SORT_STRING);
    expect($vinculados)->toBe($esperado);
    expect(collect($linhas)->pluck('periodo_inicio')->unique()->all())->toBe([DI_TESTE]);
    expect(collect($linhas)->pluck('periodo_fim')->unique()->all())->toBe([DI_TESTE]);

    $maiq = $payload['resumo']['por_termo'][0];
    expect($maiq['processos'])->toBe($esperado);
    expect($maiq['texto'])->toContain('- Processos: ');

    foreach ($maiq['processos'] as $numero) {
        expect($maiq['texto'])->toContain("**{$numero}**");
    }
});

test('vinculos por termo sobrevivem no cache', function () {
    fakeTcmHttp();
    $svc = app(SearchService::class);
    $payload = $svc->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);
    $cache = $svc->cachedPayload(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    expect($cache['resumo']['por_termo'][0]['processos'])
        ->toBe($payload['resumo']['por_termo'][0]['processos']);
    expect($cache['processos'])->not->toBeEmpty();
});

test('mesma pagina e gravada para cada periodo buscado', function () {
    fakeTcmHttp();
    $svc = app(SearchService::class);
    $amplo = ['inicio' => '2026-09-08', 'fim' => '2026-10-07'];
    $svc->execute($amplo);
    $estreito = $svc->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    expect($estreito['ocorrencias'])->toHaveCount(1);
    expect($svc->cachedPayload($amplo)['ocorrencias'])->toHaveCount(3);

    $grupos = DB::table('ocorrencias')
        ->select('periodo_inicio', DB::raw('COUNT(*) AS n'))
        ->groupBy('periodo_inicio')
        ->orderBy('periodo_inicio')
        ->pluck('n', 'periodo_inicio')
        ->map(fn ($n): int => (int) $n)
        ->all();

    expect($grupos)->toBe(['2026-09-08' => 3, '2026-09-30' => 1]);
});

test('processos nao encontrados sao persistidos', function () {
    fakeTcmHttp(['tcm_inexistente' => ['10030e25']]);
    $payload = app(SearchService::class)->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    expect($payload['processos'])->not->toBeEmpty();

    foreach ($payload['processos'] as $processo) {
        expect($processo['encontrado'])->toBeFalse();
    }

    expect($payload['resumo']['geral']['texto'])->toContain('não localizado');
});

test('falha de rede no tcm vira aviso sem persistir', function () {
    fakeTcmHttp(['tcm_quebrar' => ['10030e25']]);
    $payload = app(SearchService::class)->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    $achou = collect($payload['meta']['avisos'])->contains(
        fn (string $aviso): bool => str_contains($aviso, "falha ao consultar o processo '10030e25'")
    );
    expect($achou)->toBeTrue();

    expect(DB::table('processos')->where('numero', '10030e25')->count())->toBe(0);
    expect(array_column($payload['processos'], 'numero'))->not->toContain('10030e25');
    // a evidência no texto é forte: o vínculo fica e o processo é consultado de novo na próxima coleta
    expect(DB::table('termo_processos')->where('processo_numero', '10030e25')->exists())->toBeTrue();
});

test('periodo sem publicacao', function () {
    fakeTcmHttp();
    $payload = app(SearchService::class)->execute([
        'inicio' => '2020-01-01',
        'fim' => '2020-01-05',
    ]);

    expect($payload['edicoes'])->toBe([]);
    expect($payload['ocorrencias'])->toBe([]);
    expect($payload['processos'])->toBe([]);

    $achou = collect($payload['meta']['avisos'])->contains(
        fn (string $aviso): bool => str_contains($aviso, 'nenhuma edição')
    );
    expect($achou)->toBeTrue();
    expect($payload['resumo']['geral']['edicoes'])->toBe(0);
});

test('falha na fonte do termo vira aviso', function () {
    fakeTcmHttp(['falhar_em' => 'Maiquinique']);
    $payload = app(SearchService::class)->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    expect($payload['ocorrencias'])->toBe([]);

    $achou = collect($payload['meta']['avisos'])->contains(
        fn (string $aviso): bool => str_contains($aviso, "falha ao buscar 'Maiquinique'")
    );
    expect($achou)->toBeTrue();

    $busca = DB::table('buscas')->orderByDesc('id')->first();
    expect($busca->status)->toBe('parcial');
});

test('cache sem nova coleta', function () {
    $mock = fakeTcmHttp();
    $svc = app(SearchService::class);

    $svc->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);
    $chamadasAntes = count($mock->egba);
    $cache = $svc->cachedPayload(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    expect(count($mock->egba))->toBe($chamadasAntes);
    expect($cache['meta']['cache'])->toBeTrue();
    expect($cache['ocorrencias'])->toHaveCount(1);
    expect($cache['resumo'])->toHaveKeys(['geral', 'por_termo']);
    // o cache tambem traz os processos enriquecidos
    expect($cache['processos'])->not->toBeEmpty();
    expect($cache['resumo']['processos'])->toBe($cache['processos']);
});

test('registro de busca fica no banco', function () {
    fakeTcmHttp();
    app(SearchService::class)->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    $edicao = Edition::query()
        ->whereBetween('data', [DI_TESTE, DI_TESTE])
        ->orderByDesc('data')
        ->first();
    expect($edicao)->not->toBeNull();
    expect($edicao->id)->toBe(22622);

    $busca = DB::table('buscas')->orderByDesc('id')->first();
    expect($busca->periodo_inicio)->toBe(DI_TESTE);
    expect((int) $busca->requests)->toBe(2);
    expect((int) $busca->duracao_ms)->toBeGreaterThanOrEqual(0);
});

test('coleta registra o prazo publicado e ordena pelo vencimento', function () {
    $pagina = fixtureJson('paginas_prazos.json')['22737_22'];

    // página 22 da edição de 23/10/2026: tabela da Inspetoria que cita a Câmara de MAIQUINIQUE
    Http::fake(function ($request) use ($pagina) {
        if (! str_contains($request->url(), '/busca/busca/buscar/query/0/di:2026-10-23')) {
            return null;
        }

        return Http::response(['hits' => ['total' => 1, 'hits' => [[
            '_id' => '22737_22',
            '_source' => $pagina + ['paginas' => 40],
            'highlight' => ['conteudo' => ['Câmara Municipal de <strong>MAIQUINIQUE</strong>']],
        ]]]]);
    });
    fakeTcmHttp();

    app(SearchService::class)->execute(['inicio' => '2026-10-23', 'fim' => '2026-10-23']);

    $prazo = Prazo::query()->where('processo_numero', '30248e26')->sole();
    expect($prazo->dias)->toBe(15);
    expect($prazo->data_inicio)->toBe('2026-10-23');
    expect($prazo->data_vencimento)->toBe('2026-11-09');
    expect($prazo->estimado)->toBeTrue();
    expect(Process::query()->find('30248e26')->prazo_vencimento)->toBe('2026-11-09');
});

test('processo de outro municipio segundo o portal nao e vinculado nem gravado', function () {
    // o portal diz que o processo é de JACOBINA: contradiz a evidência do texto
    fakeTcmHttp(['tcm_html' => 'consulta_processo_00124e26.html']);

    $payload = app(SearchService::class)->execute(['inicio' => DI_TESTE, 'fim' => DI_TESTE]);

    // a ocorrência continua (Maiquinique está escrito na página), mas sem processos
    expect($payload['ocorrencias'])->toHaveCount(1);
    expect($payload['ocorrencias'][0]['processos'])->toBe([]);
    expect(DB::table('termo_processos')->count())->toBe(0);
    expect(DB::table('processos')->count())->toBe(0);
});

test('pagina em que o termo nao esta escrito nao vira ocorrencia', function () {
    $pagina = fixtureJson('paginas_prazos.json')['22737_20'];

    Http::fake(function ($request) use ($pagina) {
        if (! str_contains($request->url(), '/busca/busca/buscar/query/0/di:2026-10-23')) {
            return null;
        }

        // a fonte devolveu a página, mas "Maiquinique" não aparece nela
        return Http::response(['hits' => ['total' => 1, 'hits' => [[
            '_id' => '22737_20',
            '_source' => $pagina + ['paginas' => 40],
        ]]]]);
    });
    fakeTcmHttp();

    $payload = app(SearchService::class)->execute(['inicio' => '2026-10-23', 'fim' => '2026-10-23']);

    expect($payload['ocorrencias'])->toBe([]);
    expect(DB::table('ocorrencias')->count())->toBe(0);
    expect(DB::table('processos')->count())->toBe(0);
});
