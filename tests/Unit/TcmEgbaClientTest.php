<?php

use App\Exceptions\SourceUnavailable;
use App\Services\EgbanetClient;
use App\Services\EnrichService;

test('data br para iso', function () {
    expect(EgbanetClient::brToIso('30/09/2026'))->toBe('2026-09-30');
});

test('url do pdf', function () {
    expect(EnrichService::pdfUrl(22622))
        ->toBe('https://egbanet.egba.ba.gov.br/tcm/portal/edicoes/download/22622');
    expect(EnrichService::pdfUrl(22622, 2))
        ->toEndWith('/22622/2');
});

test('edicao atual', function () {
    fakeTcmHttp();

    $edicao = (new EgbanetClient)->currentEdition();

    expect($edicao)->not->toBeNull();
    expect($edicao->id)->toBe(22622);
    expect($edicao->data)->toBe(DI_TESTE);
    expect($edicao->numero)->toBe(2899);
    expect($edicao->paginas)->toBe(17);
});

test('edicoes recentes', function () {
    fakeTcmHttp();

    $edicoes = (new EgbanetClient)->recentEditions();
    expect($edicoes)->toHaveCount(8);

    $datas = array_map(fn ($e): string => $e->data, $edicoes);
    $decrescente = $datas;
    rsort($decrescente, SORT_STRING);

    expect($datas)->toBe($decrescente);
    expect($datas[0])->toBe(DI_TESTE);
});

test('buscar pagina monta a url', function () {
    $mock = fakeTcmHttp();

    (new EgbanetClient)->searchPage('Maiquinique', '2026-09-01', '2026-09-30', 0);
    $url = end($mock->egba);

    expect($url)->toContain('/busca/busca/buscar/query/0/di:2026-09-01/df:2026-09-30/');
    expect($url)->toContain('q=%22Maiquinique%22');
    expect($url)->toContain('subtheme=tcm');
});

test('buscar termo pagina e total', function () {
    fakeTcmHttp();

    $resultado = (new EgbanetClient)->searchMany(['Maiquinique'], DI_TESTE, DI_TESTE);

    expect($resultado['errors'])->toBe([]);
    $lote = $resultado['hits']['Maiquinique'];
    expect($lote)->toHaveCount(1);
    expect($lote[0]['_id'])->toBe('22622_2');
    expect($lote[0]['_source']['pagina'])->toBe(2);
});

test('resposta 500 vira erro tipado', function () {
    fakeTcmHttp(['falhar_em' => 'Qualquer']);

    expect(fn () => (new EgbanetClient)->searchPage('Qualquer', DI_TESTE, DI_TESTE))
        ->toThrow(SourceUnavailable::class);
});

test('fixture de busca tem conteudo e highlight', function () {
    $dados = fixtureJson('busca_candido_sales.json');
    $hit = $dados['hits']['hits'][0];

    expect($hit['_source'])->toHaveKey('conteudo');
    expect($hit['highlight']['conteudo'])->not->toBeEmpty();
    expect($hit['_source']['diario_id'])->toBe(22510);
});
