<?php

use App\Enums\PastaDaPrestacao;
use App\Enums\TipoDePagina;
use App\Services\Organizador\ArquivoDeEntrada;
use App\Services\Organizador\ClassificadorDeDocumento;
use App\Services\Organizador\DocumentoDeSaida;
use App\Services\Organizador\MontadorDeAvulsos;
use App\Services\Organizador\MontadorDeProcessos;
use App\Services\Organizador\PaginaClassificada;
use App\Services\Organizador\VarianteDaFolha;

/**
 * @param  list<PaginaClassificada>  $paginas
 */
function arquivoDeEntrada(string $relativo, array $paginas, string $texto = ''): ArquivoDeEntrada
{
    return new ArquivoDeEntrada('/tmp/'.md5($relativo).'.pdf', $relativo, $paginas, $texto, md5($relativo));
}

function capa(string $numero, string $fornecedor = '27 - COELBA - COMPANHIA', bool $extra = false): PaginaClassificada
{
    return new PaginaClassificada(
        tipo: $extra ? TipoDePagina::OrdemDePagamentoExtra : TipoDePagina::OrdemDePagamento,
        numeroProcesso: $numero,
        empenho: '2501060007/2025',
        subEmpenho: '8',
        fornecedor: $fornecedor,
    );
}

function peca(TipoDePagina $tipo, ?string $empenho = null, ?string $subEmpenho = null, ?VarianteDaFolha $folha = null): PaginaClassificada
{
    return new PaginaClassificada($tipo, empenho: $empenho, subEmpenho: $subEmpenho, folha: $folha);
}

/**
 * Number of each page in the source file, in the final order.
 *
 * @return list<int>
 */
function ordemDasPaginas(DocumentoDeSaida $documento): array
{
    return array_column($documento->paginas, 'pagina');
}

test('ordena as pecas na ordem da planilha do TCM', function () {
    $arquivo = arquivoDeEntrada('PROCESSO PAGAMENTO/PP 202501220006 COELBA.pdf', [
        capa('202501220006'),
        peca(TipoDePagina::NotaDeLiquidacao),
        peca(TipoDePagina::NotaDeSubEmpenho),
        peca(TipoDePagina::NotaDeEmpenho),
        peca(TipoDePagina::Comprovante),
        peca(TipoDePagina::Documento),
        peca(TipoDePagina::Documento),
    ]);

    $processo = (new MontadorDeProcessos)->montar([$arquivo])['processos'][0];

    expect($processo->pasta)->toBe(PastaDaPrestacao::ProcessoPagamento)
        ->and($processo->nome)->toBe('PP 202501220006 COELBA')
        ->and(ordemDasPaginas($processo))->toBe([1, 2, 4, 3, 6, 7, 5])
        ->and($processo->avisos)->toBe([]);
});

test('extra-orcamentario vai para EXTRA na ordem capa, guia e comprovante', function () {
    $arquivo = arquivoDeEntrada('scan.pdf', [
        capa('202501230008', '16 - SECRETARIA MUNICIPAL DE ADMINISTRACAO', extra: true),
        peca(TipoDePagina::Comprovante),
        peca(TipoDePagina::GuiaDeDespesa),
    ]);

    $processo = (new MontadorDeProcessos)->montar([$arquivo])['processos'][0];

    expect($processo->pasta)->toBe(PastaDaPrestacao::Extra)
        ->and($processo->nome)->toBe('PPE 202501230008 SECRETARIA MUNICIPAL DE ADMINISTRACAO')
        ->and(ordemDasPaginas($processo))->toBe([1, 3, 2]);
});

test('um arquivo com varios processos e dividido a cada capa', function () {
    $arquivo = arquivoDeEntrada('lote.pdf', [
        capa('202501220001', '3 - ELITE SOLUCOES'),
        peca(TipoDePagina::Comprovante),
        capa('202501220002', '5 - ACJUS ASSESSORIA'),
        peca(TipoDePagina::Documento),
    ]);

    $processos = (new MontadorDeProcessos)->montar([$arquivo])['processos'];

    expect(array_map(fn (DocumentoDeSaida $processo) => $processo->nome, $processos))->toBe(['PP 202501220001 ELITE', 'PP 202501220002 ACJUS'])
        ->and(ordemDasPaginas($processos[0]))->toBe([1, 2])
        ->and(ordemDasPaginas($processos[1]))->toBe([3, 4]);
});

test('unifica o mesmo processo vindo de arquivos diferentes e avisa', function () {
    $capaEmUmArquivo = arquivoDeEntrada('capa.pdf', [capa('202501220006'), peca(TipoDePagina::NotaDeLiquidacao)]);
    $restoEmOutro = arquivoDeEntrada('resto.pdf', [capa('202501220006'), peca(TipoDePagina::Comprovante)]);

    $resultado = (new MontadorDeProcessos)->montar([$capaEmUmArquivo, $restoEmOutro]);

    expect($resultado['processos'])->toHaveCount(1)
        ->and($resultado['processos'][0]->paginas)->toHaveCount(4)
        ->and($resultado['processos'][0]->avisos)->toContain('Unificado de 2 capas (capa, resto); confira páginas repetidas.');
});

test('arquivo sem capa entra no processo pelo numero no nome ou pelo empenho', function () {
    $processo = arquivoDeEntrada('PP 202501220006 COELBA.pdf', [capa('202501220006')]);
    $comprovante = arquivoDeEntrada('comprovante 202501220006.pdf', [peca(TipoDePagina::Comprovante)]);
    $notas = arquivoDeEntrada('notas.pdf', [peca(TipoDePagina::NotaDeLiquidacao, '2501060007/2025', '8')]);

    $resultado = (new MontadorDeProcessos)->montar([$processo, $comprovante, $notas]);

    expect($resultado['restantes'])->toBe([])
        ->and($resultado['processos'][0]->paginas)->toHaveCount(3);
});

test('notas sem processo correspondente ficam avulsas com aviso', function () {
    $notas = arquivoDeEntrada('notas.pdf', [peca(TipoDePagina::NotaDeEmpenho, '9999999999/2025')]);

    $resultado = (new MontadorDeProcessos)->montar([$notas]);

    expect($resultado['processos'])->toBe([])
        ->and($resultado['restantes'])->toBe([$notas])
        ->and($resultado['avisos'][0])->toContain('"notas.pdf" tem notas de empenho');
});

test('folha separa analitica e sintetica do mesmo processo', function () {
    $analitica = arquivoDeEntrada('FOLHA/ANALÍTICA/PP 202501220008 FOLHA FUNCIONARIOS ANALITICA.pdf', [
        capa('202501220008', '30 - FOLHA DE FUNCIONARIOS'),
        peca(TipoDePagina::Documento, folha: VarianteDaFolha::Analitica),
        peca(TipoDePagina::Comprovante),
    ]);
    $sintetica = arquivoDeEntrada('totais.pdf', [
        capa('202501220008', '30 - FOLHA DE FUNCIONARIOS'),
        peca(TipoDePagina::Documento, folha: VarianteDaFolha::Sintetica),
    ]);

    $processos = (new MontadorDeProcessos)->montar([$analitica, $sintetica])['processos'];

    expect($processos)->toHaveCount(2)
        ->and($processos[0]->pasta)->toBe(PastaDaPrestacao::FolhaAnalitica)
        ->and($processos[0]->nome)->toBe('PP 202501220008 FOLHA FUNCIONARIOS ANALITICA')
        ->and($processos[1]->pasta)->toBe(PastaDaPrestacao::FolhaSintetica)
        ->and($processos[1]->nome)->toBe('PP 202501220008 FOLHA DE FUNCIONARIOS SINTETICA');
});

test('avisa as pecas obrigatorias que faltam', function () {
    $arquivo = arquivoDeEntrada('PP 202501220006 COELBA.pdf', [capa('202501220006'), peca(TipoDePagina::NotaDeLiquidacao)]);

    $avisos = (new MontadorDeProcessos)->montar([$arquivo])['processos'][0]->avisos;

    expect($avisos)->toBe(['Sem Nota de Empenho.', 'Sem Nota de Sub-empenho.', 'Sem Comprovante de Pagamento.']);
});

test('avulsos em partes sao unificados na ordem das partes', function () {
    $parte2 = arquivoDeEntrada('INEX 001-2025 - ACJUS - Parte 2.pdf', [peca(TipoDePagina::Documento)]);
    $parte1 = arquivoDeEntrada('INEX 001-2025 - ACJUS - Parte 1.pdf', [peca(TipoDePagina::Documento), peca(TipoDePagina::Documento)]);
    $contrato = arquivoDeEntrada('CONTRATO 001-2025.pdf', [peca(TipoDePagina::Documento)]);

    $documentos = (new MontadorDeAvulsos(new ClassificadorDeDocumento))->montar([$parte2, $parte1, $contrato]);

    expect($documentos)->toHaveCount(2)
        ->and($documentos[0]->nome)->toBe('INEX 001-2025 - ACJUS')
        ->and($documentos[0]->pasta)->toBe(PastaDaPrestacao::InexEDispensas)
        ->and(array_column($documentos[0]->paginas, 'arquivo'))->toBe([$parte1->caminho, $parte1->caminho, $parte2->caminho])
        ->and($documentos[0]->arquivoInteiro)->toBeNull()
        ->and($documentos[1]->arquivoInteiro)->toBe($contrato->caminho);
});
