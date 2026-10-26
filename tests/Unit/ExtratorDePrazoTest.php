<?php

use App\Enums\ContagemDePrazo;
use App\Services\EnrichService;
use App\Services\ExtratorDePrazo;
use Carbon\CarbonImmutable;

/**
 * Real page texts (EGBANET search, October 2026) keyed by "<diario_id>_<pagina>".
 */
function paginaDePrazos(string $id): string
{
    return fixtureJson('paginas_prazos.json')[$id]['conteudo'];
}

/**
 * @return array<string, array<string, mixed>>
 */
function prazosDaPagina(string $id): array
{
    $conteudo = paginaDePrazos($id);

    return ExtratorDePrazo::extrair($conteudo, EnrichService::extractProcessNumbers($conteudo));
}

test('edital nominal conta da publicacao', function () {
    $prazo = prazosDaPagina('22737_21')['22623e23'];

    expect($prazo['dias'])->toBe(20);
    expect($prazo['contagem'])->toBe(ContagemDePrazo::Corridos);
    expect($prazo['a_partir_da_publicacao'])->toBeTrue();
    expect($prazo['trecho'])->toContain('prazo regimental de 20 (vinte) dias corridos');
});

test('tabela da inspetoria usa a clausula do cabecalho na mesma pagina', function () {
    $prazos = prazosDaPagina('22737_21');

    expect($prazos['25798e26']['dias'])->toBe(15);
    expect($prazos['25798e26']['a_partir_da_publicacao'])->toBeFalse();
    expect($prazos['25798e26']['trecho'])->toContain('prestação de contas do período');
    expect($prazos['28889e26']['dias'])->toBe(15);
});

test('tabela da inspetoria sem cabecalho na pagina usa o prazo padrao', function () {
    config(['tcm.inspectorate_notice_days' => 15]);

    $prazos = prazosDaPagina('22737_22');

    // Olindina: a linha está na página 22, o cabeçalho do edital ficou na 21
    expect($prazos['31117e26']['dias'])->toBe(15);
    expect($prazos['31117e26']['a_partir_da_publicacao'])->toBeFalse();
    expect($prazos)->toHaveCount(42);
});

test('cada edital nominal fica com o seu prazo', function () {
    $prazos = prazosDaPagina('22737_20');

    expect($prazos['31856e26']['dias'])->toBe(5);
    expect($prazos['31856e26']['a_partir_da_publicacao'])->toBeFalse();
    expect($prazos['32270e26']['dias'])->toBe(20);
    expect($prazos['22443e23']['dias'])->toBe(20);
});

test('processo cuja clausula ficou na pagina anterior fica sem prazo', function () {
    expect(prazosDaPagina('22722_23'))->not->toHaveKey('32124e26');
});

test('so os processos pedidos sao considerados', function () {
    $prazos = ExtratorDePrazo::extrair(paginaDePrazos('22737_21'), ['22623e23']);

    expect(array_keys($prazos))->toBe(['22623e23']);
});

test('prazo por extenso e dias uteis', function () {
    $texto = 'DETERMINO a notificação do Prefeito, Processo nº 12345e26, para que se manifeste no prazo de cinco dias úteis, contados da publicação.';

    $prazo = ExtratorDePrazo::extrair($texto, ['12345e26'])['12345e26'];

    expect($prazo['dias'])->toBe(5);
    expect($prazo['contagem'])->toBe(ContagemDePrazo::Uteis);
    expect($prazo['a_partir_da_publicacao'])->toBeTrue();
});

test('prazo que ja transcorreu ou que nao e ordem a parte e ignorado', function () {
    $narrativa = 'Processo nº 12345e26. Concedeu-se o prazo de dez dias úteis ao Gestor, o qual transcorreu sem resposta.';
    $contrato = 'Processo nº 12345e26 - Contrato com prazo de 30 dias para entrega dos equipamentos.';

    expect(ExtratorDePrazo::extrair($narrativa, ['12345e26']))->toBe([]);
    expect(ExtratorDePrazo::extrair($contrato, ['12345e26']))->toBe([]);
});

test('vencimento em dias corridos pula o fim de semana', function () {
    // publicado sexta 23/10/2026: +15 = sábado 07/11 -> segunda 09/11
    $vencimento = ContagemDePrazo::Corridos->vencimento(CarbonImmutable::parse('2026-10-23'), 15);

    expect($vencimento->format('Y-m-d'))->toBe('2026-11-09');
    expect(ContagemDePrazo::Corridos->vencimento(CarbonImmutable::parse('2026-10-23'), 20)->format('Y-m-d'))->toBe('2026-11-12');
});

test('vencimento em dias uteis ignora sabados e domingos', function () {
    $vencimento = ContagemDePrazo::Uteis->vencimento(CarbonImmutable::parse('2026-10-23'), 5);

    expect($vencimento->format('Y-m-d'))->toBe('2026-10-30');
});

test('despachos de prorrogacao tambem geram prazo', function () {
    $prazos = prazosDaPagina('22737_19');

    // "Fica deferido o prazo de 05 (cinco) dias, a contar da data de publicação"
    expect($prazos['03450e26']['dias'])->toBe(5);
    expect($prazos['03450e26']['a_partir_da_publicacao'])->toBeTrue();
    // "concedendo mais 10 (dez) dias para apresentação de defesa do processo n°25395e26"
    expect($prazos['25395e26']['dias'])->toBe(10);
    expect($prazos['25395e26']['a_partir_da_publicacao'])->toBeTrue();
});
