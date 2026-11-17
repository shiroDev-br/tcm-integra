<?php

use App\Enums\PastaDaPrestacao;
use App\Enums\TipoDePagina;
use App\Services\Organizador\ClassificadorDeDocumento;
use App\Services\Organizador\ClassificadorDePagina;
use App\Services\Organizador\NomeDeArquivo;
use App\Services\Organizador\VarianteDaFolha;

/**
 * Real page texts (OCR of the "CM de Bom Jesus da Serra" delivery, January 2025), CPFs masked.
 */
function paginaDoOrganizador(string $chave): string
{
    return fixtureJson('organizador_paginas.json')[$chave];
}

test('reconhece cada peca do processo de pagamento', function (string $chave, TipoDePagina $tipo) {
    expect((new ClassificadorDePagina)->classificar(paginaDoOrganizador($chave))->tipo)->toBe($tipo);
})->with([
    'capa orcamentaria' => ['capa_pp', TipoDePagina::OrdemDePagamento],
    'capa extra-orcamentaria' => ['capa_ppe', TipoDePagina::OrdemDePagamentoExtra],
    'nota de liquidacao' => ['liquidacao', TipoDePagina::NotaDeLiquidacao],
    'nota de empenho' => ['empenho', TipoDePagina::NotaDeEmpenho],
    'nota de sub-empenho' => ['sub_empenho', TipoDePagina::NotaDeSubEmpenho],
    'comprovante de boleto' => ['comprovante_boleto', TipoDePagina::Comprovante],
    'comprovante pix' => ['comprovante_pix', TipoDePagina::Comprovante],
    'comprovante ted' => ['comprovante_ted', TipoDePagina::Comprovante],
    'guia DAM' => ['guia_dam', TipoDePagina::GuiaDeDespesa],
    'nota fiscal de energia' => ['nota_fiscal_energia', TipoDePagina::Documento],
    'certidao' => ['certidao_fgts', TipoDePagina::Documento],
]);

test('textos que so citam ordem de pagamento ou nota de empenho nao viram pecas', function (string $chave) {
    expect((new ClassificadorDePagina)->classificar(paginaDoOrganizador($chave))->tipo)->toBe(TipoDePagina::Documento);
})->with(['listagem_ordens', 'regimento_cita_ordem', 'dispensa_cita_empenho']);

test('capa traz numero do processo, fornecedor e empenho', function () {
    $capa = (new ClassificadorDePagina)->classificar(paginaDoOrganizador('capa_pp'));

    expect($capa->numeroProcesso)->toBe('202501220006')
        ->and($capa->fornecedor)->toStartWith('COELBA - COMPANHIA DE ELETRICIDADE')
        ->and($capa->empenho)->toBe('2501060007/2025')
        ->and($capa->subEmpenho)->toBe('8');
});

test('capa extra-orcamentaria traz o numero do processo', function () {
    $capa = (new ClassificadorDePagina)->classificar(paginaDoOrganizador('capa_ppe'));

    expect($capa->numeroProcesso)->toBe('202501230008')
        ->and($capa->fornecedor)->toBe('SECRETARIA MUNICIPAL DE ADMINISTRACAO');
});

test('distingue folha analitica de sintetica', function () {
    $classificador = new ClassificadorDePagina;

    expect($classificador->classificar(paginaDoOrganizador('folha_analitica'))->folha)->toBe(VarianteDaFolha::Analitica)
        ->and($classificador->classificar(paginaDoOrganizador('folha_sintetica'))->folha)->toBe(VarianteDaFolha::Sintetica)
        ->and($classificador->classificar(paginaDoOrganizador('certidao_fgts'))->folha)->toBeNull();
});

test('documento avulso vai para a pasta pelo nome, pela pasta de origem ou pelo conteudo', function (string $caminho, string $texto, PastaDaPrestacao $pasta) {
    expect((new ClassificadorDeDocumento)->pasta($caminho, $texto === '' ? '' : paginaDoOrganizador($texto)))->toBe($pasta);
})->with([
    'recibo do TCM pelo nome' => ['reciboPrestacaoContas.pdf', '', PastaDaPrestacao::Raiz],
    'contrato pelo nome' => ['CONTRATO 001-2025.pdf', '', PastaDaPrestacao::ContratosEAditivos],
    'inexigibilidade pelo nome' => ['INEX 001-2025 - ACJUS - Parte 1.pdf', '', PastaDaPrestacao::InexEDispensas],
    'declaracao de inexistencia nao e inex' => ['Declaração de Inexistência.pdf', '', PastaDaPrestacao::RelatoriosDiversos],
    'pela pasta de origem' => ['SIGA - ok/Demonstrativo da Despesa Orçamentária.pdf', '', PastaDaPrestacao::Siga],
    'siga pelo conteudo mesmo chamado de relatorio' => ['relatorio tcm.pdf', 'siga', PastaDaPrestacao::Siga],
    'recibo pelo conteudo' => ['documento.pdf', 'recibo_tcm', PastaDaPrestacao::Raiz],
    'inexigibilidade pelo conteudo' => ['scan001.pdf', 'inexigibilidade', PastaDaPrestacao::InexEDispensas],
    'contrato pelo conteudo' => ['scan002.pdf', 'contrato', PastaDaPrestacao::ContratosEAditivos],
    'sem pista vai para relatorios diversos' => ['Resumo Bancário.pdf', '', PastaDaPrestacao::RelatoriosDiversos],
]);

test('nome curto do fornecedor segue o padrao de entrega', function (string $fornecedor, string $esperado) {
    expect(NomeDeArquivo::fornecedorCurto($fornecedor))->toBe($esperado);
})->with([
    ['COELBA - COMPANHIA DE ELETRICIDADE DO ESTADO DA BAHIA', 'COELBA'],
    ['ELITE SOLUÇÕES CONTÁBEIS LTDA', 'ELITE'],
    ['SECRETARIA MUNICIPAL DE ADMINISTRAÇÃO', 'SECRETARIA MUNICIPAL DE ADMINISTRACAO'],
    ['FOLHA DE FUNCIONÁRIOS', 'FOLHA DE FUNCIONARIOS'],
]);

test('partes do nome do arquivo', function () {
    expect(NomeDeArquivo::parte('INEX 001-2025 - ACJUS - Parte 2'))->toBe(2)
        ->and(NomeDeArquivo::semParte('INEX 001-2025 - ACJUS - Parte 2'))->toBe('INEX 001-2025 - ACJUS')
        ->and(NomeDeArquivo::comParte('PP 202501220002 ACJUS', 1))->toBe('PP 202501220002 ACJUS - Parte 1')
        ->and(NomeDeArquivo::parte('CONTRATO 001-2025'))->toBeNull();
});
