<?php

use App\Services\ResumoService;

function resumoTermos(): array
{
    return [
        ['id' => 1, 'termo' => 'Maiquinique', 'tipo' => 'municipio'],
        ['id' => 2, 'termo' => 'Plenary', 'tipo' => 'empresa'],
    ];
}

/**
 * @param  array<string, mixed>  $sobrescrever
 */
function resumoOcorrencia(array $sobrescrever = []): array
{
    return array_merge([
        'termo' => 'Maiquinique',
        'termo_id' => 1,
        'tipo_termo' => 'municipio',
        'edicao_id' => 22622,
        'data' => '2026-09-30',
        'pagina' => 2,
        'tipo' => 'ato',
        'trecho' => 'Prefeitura Municipal de MAIQUINIQUE',
        'contexto' => 'Prefeitura Municipal de MAIQUINIQUE',
        'atos' => ['Processo nº 11966e21', 'Prefeitura Municipal de MAIQUINIQUE'],
        'processos' => [],
        'ocorrencias_na_pagina' => 1,
        'pdf_url' => 'https://egbanet.egba.ba.gov.br/tcm/portal/edicoes/download/22622/2',
    ], $sobrescrever);
}

function resumoProcessoLocalizado(): array
{
    return [
        'numero' => '09768e26',
        'encontrado' => true,
        'data_entrada' => '07/04/2026',
        'autor' => 'Flávio Macário - Delegado',
        'complemento' => 'Representação em face da Prefeitura Municipal de Aporá.',
        'situacao' => 'Em Andamento',
        'situacao_data' => '30/09/2026',
        'meio' => 'e-TCM',
        'andamento' => [['unidade' => 'GP', 'data' => '30/09/2026', 'situacao' => 'Em Andamento']],
        'consulta_url' => 'https://www.tcm.ba.gov.br/x',
        'consultado_em' => '2026-09-30T12:00:00-03:00',
    ];
}

function resumoProcessoAusente(): array
{
    return [
        'numero' => '11111e11',
        'encontrado' => false,
        'data_entrada' => null,
        'autor' => null,
        'complemento' => null,
        'situacao' => null,
        'situacao_data' => null,
        'meio' => null,
        'andamento' => [],
        'consulta_url' => null,
        'consultado_em' => null,
    ];
}

test('resumo com ocorrencia e vazio', function () {
    $resumo = ResumoService::build(
        [resumoOcorrencia()],
        resumoTermos(),
        1,
        '2026-09-30',
        '2026-09-30',
        [],
        []
    );

    expect($resumo['geral']['edicoes'])->toBe(1);
    expect($resumo['geral']['termos'])->toBe(2);
    expect($resumo['geral']['termos_com_ocorrencia'])->toBe(1);
    expect($resumo['geral']['paginas'])->toBe(1);
    expect($resumo['geral']['ocorrencias'])->toBe(1);
    expect($resumo['geral']['texto'])->not->toContain('3 de 2');
    expect($resumo['geral']['texto'])->toContain('**Maiquinique**');
    expect($resumo['geral']['texto'])->toContain('Sem ocorrência: Plenary.');

    [$maiq, $plenary] = $resumo['por_termo'];

    expect($maiq['ocorrencias'])->toBe(1);
    expect($maiq['paginas'])->toBe(1);
    expect($maiq['datas'])->toBe(['2026-09-30']);
    expect($maiq['itens'][0])->toContain('Processo nº 11966e21');
    expect($maiq['itens'][0])->toContain('30/09/2026');
    expect($maiq['itens'][0])->toContain('[PDF](');
    expect($plenary['ocorrencias'])->toBe(0);
    expect($plenary['texto'])->toContain('nada encontrado');
});

test('resumo sem nenhuma ocorrencia', function () {
    $resumo = ResumoService::build([], resumoTermos(), 0, '2026-09-30', '2026-09-30', [], []);

    expect($resumo['geral']['termos_com_ocorrencia'])->toBe(0);
    expect($resumo['geral']['texto'])->toContain('Nenhum termo encontrado no período.');

    foreach ($resumo['por_termo'] as $termo) {
        expect($termo['texto'])->toContain('nada encontrado');
    }
});

test('resumo agrupa varias paginas', function () {
    $ocorrencias = [
        resumoOcorrencia(),
        resumoOcorrencia([
            'pagina' => 13,
            'tipo' => 'mencao',
            'atos' => [],
            'trecho' => 'menção na página 13',
            'pdf_url' => 'https://egbanet.egba.ba.gov.br/tcm/portal/edicoes/download/22622/13',
        ]),
    ];

    $resumo = ResumoService::build($ocorrencias, resumoTermos(), 1, '2026-09-30', '2026-09-30', [], []);
    $maiq = $resumo['por_termo'][0];

    expect($maiq['paginas'])->toBe(2);
    expect($maiq['ocorrencias'])->toBe(2);
    expect($maiq['texto'])->not->toContain('2 com ato identificado'); // 1 ato + 1 mencao
    expect($maiq['texto'])->toContain('1 com ato identificado');
});

test('resumo sem processos omite a secao', function () {
    $resumo = ResumoService::build(
        [resumoOcorrencia()],
        resumoTermos(),
        1,
        '2026-09-30',
        '2026-09-30',
        [],
        []
    );

    expect($resumo['processos'])->toBe([]);
    expect($resumo['geral']['texto'])->not->toContain('Processos envolvidos');
});

test('resumo lista processos por termo', function () {
    $ocorrencia = resumoOcorrencia(['processos' => ['09768e26', '11111e11']]);
    $vinculos = [1 => ['11111e11', '17799e23']];

    $resumo = ResumoService::build(
        [$ocorrencia],
        resumoTermos(),
        1,
        '2026-09-30',
        '2026-09-30',
        [],
        $vinculos
    );

    [$maiq, $plenary] = $resumo['por_termo'];

    expect($maiq['processos'])->toBe(['09768e26', '11111e11', '17799e23']);
    expect($maiq['texto'])
        ->toContain('- Processos: **09768e26**, **11111e11**, **17799e23**');
    expect($plenary['processos'])->toBe([]);
    expect($plenary['texto'])->not->toContain('Processos: ');
});

test('resumo mostra vinculo mesmo sem ocorrencia no periodo', function () {
    $resumo = ResumoService::build(
        [],
        resumoTermos(),
        1,
        '2026-09-30',
        '2026-09-30',
        [],
        [2 => ['09768e26']]
    );

    [$maiq, $plenary] = $resumo['por_termo'];

    expect($maiq['processos'])->toBe([]);
    expect($plenary['processos'])->toBe(['09768e26']);
    expect($plenary['texto'])->toContain('nada encontrado no período');
    expect($plenary['texto'])->toContain('**09768e26**');
});

test('resumo com processos enriquecidos', function () {
    $localizado = resumoProcessoLocalizado();
    $ausente = resumoProcessoAusente();

    $resumo = ResumoService::build(
        [resumoOcorrencia()],
        resumoTermos(),
        1,
        '2026-09-30',
        '2026-09-30',
        [$localizado, $ausente],
        []
    );
    $texto = $resumo['geral']['texto'];

    expect($resumo['processos'])->toBe([$localizado, $ausente]);
    expect($texto)->toContain('### Processos envolvidos (2)');
    expect($texto)->toContain('**09768e26** · entrada 07/04/2026 · Em Andamento (30/09/2026)');
    expect($texto)->toContain('[abrir consulta](https://www.tcm.ba.gov.br/x)');
    expect($texto)->toContain('Autor: Flávio Macário - Delegado');
    expect($texto)->toContain('**11111e11** — não localizado na consulta-processual.');
    expect($texto)->toContain('Processos citados: 2 — 1 localizados na consulta-processual');
});
