<?php

use App\Exceptions\SourceUnavailable;
use App\Services\TcmProcessClient;

test('parse processo encontrado', function () {
    $html = fixtureHtml('consulta_processo_09768e26.html');
    $proc = TcmProcessClient::parseLookup($html, '09768e26', (string) config('tcm.process_lookup_url'));

    expect($proc['encontrado'])->toBeTrue();
    expect($proc['numero'])->toBe('09768e26');
    expect($proc['data_entrada'])->toBe('07/04/2026');
    expect($proc['autor'])->toBe('Flávio Macário de Carvalho - Delegado');
    expect($proc['complemento'])->toContain('Prefeitura Municipal de Aporá');
    expect($proc['situacao_data'])->toBe('30/09/2026');
    expect($proc['meio'])->toBe('e-TCM');
    // andamento: mais recente primeiro (GP 30/09 "Em Andamento")
    expect($proc['andamento'])->toHaveCount(6);
    expect($proc['andamento'][0]['unidade'])->toBe('GP');
    expect($proc['andamento'][0]['situacao'])->toBe('Em Andamento');
    expect($proc['consultado_em'])->not->toBeNull();
    expect($proc['consulta_url'])->toBe(config('tcm.process_lookup_url'));
});

test('parse processo inexistente', function () {
    $html = fixtureHtml('consulta_processo_inexistente.html');
    $proc = TcmProcessClient::parseLookup($html, '99999e99', (string) config('tcm.process_lookup_url'));

    expect($proc['encontrado'])->toBeFalse();
    expect($proc['numero'])->toBe('99999e99');
    expect($proc['data_entrada'])->toBeNull();
    expect($proc['andamento'])->toBe([]);
});

test('parse markup irreconhecivel vira erro', function () {
    expect(fn (): array => TcmProcessClient::parseLookup(
        '<html><body>qualquer coisa</body></html>',
        '1e11',
        'u'
    ))->toThrow(SourceUnavailable::class);
});

test('consultar monta o post', function () {
    $mock = fakeTcmHttp();

    $proc = (new TcmProcessClient)->lookup('09768e26');

    expect($proc['encontrado'])->toBeTrue();
    expect($mock->tcm)->toBe(['09768e26']);
});

test('consultar inexistente e nao erro', function () {
    fakeTcmHttp(['tcm_inexistente' => ['00000e00']]);

    $proc = (new TcmProcessClient)->lookup('00000e00');

    expect($proc['encontrado'])->toBeFalse();
});

test('parse le o municipio quando o portal informa', function () {
    $html = fixtureHtml('consulta_processo_00124e26.html');
    $proc = TcmProcessClient::parseLookup($html, '00124e26', (string) config('tcm.process_lookup_url'));

    expect($proc['municipio'])->toBe('JACOBINA');
    // processos sem o campo (prestação de contas, layout antigo) ficam sem município
    $antigo = TcmProcessClient::parseLookup(fixtureHtml('consulta_processo_09768e26.html'), '09768e26', '');
    expect($antigo['municipio'])->toBeNull();
});
