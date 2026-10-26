<?php

use App\Enums\OccurrenceType;
use App\Services\EnrichService;

test('normalizar remove acentos e caixa', function () {
    expect(EnrichService::normalize('Cândido  SALES'))->toBe('candido sales');
    expect(EnrichService::normalize('Itajuípe'))->toBe('itajuipe');
});

test('limpar highlight', function () {
    expect(EnrichService::cleanHighlight('<strong>CÂNDIDO</strong> <strong>SALES</strong>'))
        ->toBe('CÂNDIDO SALES');
});

test('extrair contexto acha posicao insensivel a acento', function () {
    $conteudo = 'x'.str_repeat('x', 99)
        .' Prefeitura Municipal de CÂNDIDO SALES. '
        .str_repeat('y', 500);

    [$contexto, $pos] = EnrichService::extractContext($conteudo, 'candido sales', 40);

    expect($pos)->toBe(125);
    expect($contexto)->toContain('CÂNDIDO SALES');
    expect(strlen($contexto))->toBeLessThan(strlen($conteudo));
});

test('extrair contexto fallback quando nao acha', function () {
    [$contexto, $pos] = EnrichService::extractContext('apenas um texto qualquer', 'termo inexistente');

    expect($pos)->toBe(-1);
    expect($contexto)->toStartWith('apenas um texto');
});

test('contar na pagina', function () {
    $conteudo = 'Prefeitura de Boa Nova. BOA NOVA é município. boa-nova não conta.';

    expect(EnrichService::countOnPage($conteudo, 'Boa Nova'))->toBe(2);
});

test('extrair atos e classificacao', function () {
    $janela = 'Processo e-TCM nº 14836e26 - Prefeitura Municipal de CÂNDIDO SALES. '
        .'Foi lavrado o Acórdão nº 19766e22APR. Pregão Eletrônico nº 077/2026.';

    $atos = EnrichService::extractActs($janela);

    expect(collect($atos)->contains(fn (string $a): bool => str_starts_with($a, 'Processo e-TCM nº 14836e26')))
        ->toBeTrue();
    expect($atos)->toContain('Acórdão nº 19766e22APR');
    expect($atos)->toContain('Pregão Eletrônico nº 077/2026');
    expect(collect($atos)->contains(fn (string $a): bool => str_contains($a, 'Prefeitura Municipal de CÂNDIDO SALES')))
        ->toBeTrue();
    expect(EnrichService::classify($atos))->toBe(OccurrenceType::Act);
    expect(EnrichService::classify([]))->toBe(OccurrenceType::Mention);
});

test('enriquecer hit real', function () {
    $dados = fixtureJson('busca_candido_sales.json');
    $hit = collect($dados['hits']['hits'])->firstWhere('_id', '22510_2');

    $occ = EnrichService::enrich($hit, 'Cândido Sales');

    expect($occ['edicao_id'])->toBe(22510);
    expect($occ['pagina'])->toBe(2);
    expect($occ['pdf_url'])->toEndWith('/22510/2');
    expect($occ['trecho'])->toContain('CÂNDIDO SALES');
    expect($occ['contexto'])->toContain('CÂNDIDO SALES');
    expect($occ['tipo'])->toBe(OccurrenceType::Act);
    expect(collect($occ['atos'])->contains(fn (string $a): bool => str_starts_with($a, 'Processo nº 11966e21')))
        ->toBeTrue();
    expect($occ['ocorrencias_na_pagina'])->toBe(1);
    expect(strlen($occ['contexto']))
        ->toBeLessThanOrEqual(strlen($hit['_source']['conteudo']));
});

test('enriquecer mencao sem atos', function () {
    $hit = [
        '_id' => '1_1',
        '_source' => [
            'conteudo' => 'Texto corrido sobre a cidade de Itarantim, sem processos aqui.',
            'diario_id' => 1,
            'pagina' => 1,
        ],
        'highlight' => ['conteudo' => ['cidade de <strong>Itarantim</strong>']],
    ];

    $occ = EnrichService::enrich($hit, 'Itarantim');

    expect($occ['tipo'])->toBe(OccurrenceType::Mention);
    expect($occ['atos'])->toBe([]);
    expect($occ['contexto'])->toContain('Itarantim');
    expect($occ['processos'])->toBe([]);
});

test('extrair processos soltos e sem acentos', function () {
    // numeros soltos no diario (sem "nº"), como no hit real de Itajuipe
    $janela = '...PREFEITURA MUNICIPAL DE APORÁ 09768e26 FLÁVIO MACÁRIO... '
        .'APORÁ 19766e22APR de 2022 e de novo 09768e26.';

    expect(EnrichService::extractProcessNumbers($janela))->toBe(['09768e26', '19766e22']);
});

test('extrair processos ignora demais numeros', function () {
    expect(EnrichService::extractProcessNumbers('Edição 2899, página 4, em 30/09/2026, nº 077/2026'))
        ->toBe([]);
});

test('enriquecer pega processos do texto', function () {
    $hit = [
        '_id' => '1_1',
        '_source' => [
            'conteudo' => 'PREFEITURA MUNICIPAL DE APORÁ 09768e26 e Pregão nº 077/2026.',
            'diario_id' => 1,
            'pagina' => 1,
        ],
        'highlight' => ['conteudo' => ['PREFEITURA MUNICIPAL DE <strong>APORÁ</strong>']],
    ];

    $occ = EnrichService::enrich($hit, 'Aporá');

    expect($occ['processos'])->toBe(['09768e26']);
});
