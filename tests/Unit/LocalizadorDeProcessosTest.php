<?php

use App\Enums\EvidenciaDoVinculo;
use App\Enums\TermType;
use App\Enums\VereditoDoPortal;
use App\Models\Process;
use App\Services\EnrichService;
use App\Services\Evidencia;
use App\Services\LocalizadorDeProcessos;

/**
 * @return array<string, string> number => evidence of the term on a real page
 */
function evidenciasNaPagina(string $id, string $termo): array
{
    $conteudo = fixtureJson('paginas_prazos.json')[$id]['conteudo'];

    return array_map(
        fn (Evidencia $evidencia): string => $evidencia->forca->value,
        LocalizadorDeProcessos::evidencias($conteudo, $termo)
    );
}

test('na tabela da inspetoria so a linha do municipio e vinculada', function () {
    // a página lista 42 câmaras; antes todas eram ligadas a Maiquinique
    expect(evidenciasNaPagina('22737_22', 'Maiquinique'))->toBe(['30248e26' => 'forte']);
    expect(evidenciasNaPagina('22737_22', 'Itarantim'))->toBe(['30244e26' => 'forte']);
});

test('linha embaralhada pelas colunas do pdf nao herda o municipio da vizinha', function () {
    // "31123e26 30250e26 LUIZ ... PIRIPÁ 05/2026 a 08/2026 ANGELA ... SÁTIRO DIAS"
    expect(evidenciasNaPagina('22737_22', 'Piripá'))->toBe(['30250e26' => 'forte']);
    // o texto de Sátiro Dias caiu depois do período de 30250e26: só fraca, o portal decide
    expect(evidenciasNaPagina('22737_22', 'Sátiro Dias'))->toBe(['30250e26' => 'fraca']);
});

test('celula quebrada depois do periodo e evidencia fraca', function () {
    // "25798e26 DÉBORA ... Prefeitura Municipal 01/2026 a 04/2026 de LAURO DE FREITAS"
    expect(evidenciasNaPagina('22737_21', 'Lauro de Freitas'))->toBe(['25798e26' => 'fraca']);
});

test('municipio antes do numero no edital e evidencia fraca', function () {
    expect(evidenciasNaPagina('22737_21', 'Feira de Santana'))->toBe(['22623e23' => 'fraca']);
    expect(evidenciasNaPagina('22737_19', 'Mundo Novo'))->toBe(['32450e26' => 'forte', '25395e26' => 'fraca']);
});

test('lista de decisoes vincula so o processo do municipio', function () {
    $hit = collect(fixtureJson('busca_maiquinique_p0.json')['hits']['hits'])->firstWhere('_id', '22622_2');

    $evidencias = LocalizadorDeProcessos::evidencias($hit['_source']['conteudo'], 'Maiquinique');

    // "Processo nº 10030e25 - Contas da Prefeitura Municipal de MAIQUINIQUE"; o acórdão vizinho fica de fora
    expect(array_keys($evidencias))->toBe(['10030e25']);
});

test('termo casa sem acento e sem caixa, mas so a palavra inteira', function () {
    $padrao = EnrichService::termPattern('Itajuípe');

    expect(preg_match($padrao, 'Prefeitura Municipal de ITAJUIPE.'))->toBe(1);
    expect(preg_match(EnrichService::termPattern('Boa Nova'), 'Câmara de Boa  Nova'))->toBe(1);
    expect(preg_match(EnrichService::termPattern('Boa Nova'), 'Câmara de Boa Novaes'))->toBe(0);
    expect(EnrichService::termPattern('  '))->toBeNull();
});

test('veredito do portal pelo municipio, autor ou complemento', function () {
    $jacobina = new Process(['municipio' => 'JACOBINA', 'autor' => 'IRCE23 - Jacobina']);
    $semMunicipio = new Process(['autor' => 'DANILO FREITAS MENDONCA']);
    $prefeitura = new Process(['autor' => 'Prefeitura Municipal de Maiquinique']);

    expect($jacobina->vereditoPara(TermType::Municipio, ['Jacobina']))->toBe(VereditoDoPortal::Confirma);
    expect($jacobina->vereditoPara(TermType::Municipio, ['Maiquinique']))->toBe(VereditoDoPortal::Contradiz);
    expect($semMunicipio->vereditoPara(TermType::Municipio, ['Maiquinique']))->toBe(VereditoDoPortal::Neutro);
    expect($prefeitura->vereditoPara(TermType::Municipio, ['Maiquinique']))->toBe(VereditoDoPortal::Confirma);
    // o município de um processo não contradiz uma empresa
    expect($jacobina->vereditoPara(TermType::Empresa, ['Alfacon']))->toBe(VereditoDoPortal::Neutro);
});

test('evidencia forte cai so com contradicao; fraca exige confirmacao', function () {
    expect(EvidenciaDoVinculo::Forte->aceita(VereditoDoPortal::Confirma))->toBeTrue();
    expect(EvidenciaDoVinculo::Forte->aceita(VereditoDoPortal::Neutro))->toBeTrue();
    expect(EvidenciaDoVinculo::Forte->aceita(VereditoDoPortal::Contradiz))->toBeFalse();
    expect(EvidenciaDoVinculo::Fraca->aceita(VereditoDoPortal::Confirma))->toBeTrue();
    expect(EvidenciaDoVinculo::Fraca->aceita(VereditoDoPortal::Neutro))->toBeFalse();
});

test('gestor informado pelo portal escrito junto ao termo confirma o vinculo', function () {
    // linha embaralhada: "LEANDRO JUNQUILHO CUNHA Prefeitura Municipal de ITAJUÍPE 05/2026 a 08/2026 29915e26"
    $trecho = 'LEANDRO JUNQUILHO CUNHA Prefeitura Municipal de ITAJUÍPE 05/2026 a 08/2026 ';
    $doMunicipio = new Process(['autor' => 'LEANDRO JUNQUILHO CUNHA, ALVARO LUIZ FERREIRA']);
    $deOutro = new Process(['autor' => 'ELDER MARQUES FONTES']);

    expect($doMunicipio->vereditoPara(TermType::Municipio, ['Itajuípe'], $trecho))->toBe(VereditoDoPortal::Confirma);
    expect($deOutro->vereditoPara(TermType::Municipio, ['Itajuípe'], $trecho))->toBe(VereditoDoPortal::Neutro);
});
