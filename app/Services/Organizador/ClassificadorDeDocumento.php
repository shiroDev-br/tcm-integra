<?php

namespace App\Services\Organizador;

use App\Enums\PastaDaPrestacao;

/**
 * Decide a pasta de um documento que não é processo de pagamento.
 *
 * Ordem de confiança: nome do arquivo, pastas onde ele veio, texto da primeira página.
 */
class ClassificadorDeDocumento
{
    public function pasta(string $caminhoRelativo, string $textoDaPrimeiraPagina): PastaDaPrestacao
    {
        $pastasDeOrigem = dirname($caminhoRelativo);

        return $this->aplicar($this->regrasDeNome(), basename($caminhoRelativo))
            ?? $this->aplicar($this->regrasDeNome(), $pastasDeOrigem === '.' ? '' : $pastasDeOrigem)
            ?? $this->aplicar($this->regrasDeConteudo(), Texto::cabecalho(Texto::normalizar($textoDaPrimeiraPagina)))
            ?? PastaDaPrestacao::RelatoriosDiversos;
    }

    /**
     * @param  list<array{0: PastaDaPrestacao, 1: list<string>}>  $regras
     */
    private function aplicar(array $regras, string $texto): ?PastaDaPrestacao
    {
        $normalizado = Texto::normalizar($texto);

        if ($normalizado === '') {
            return null;
        }

        foreach ($regras as [$pasta, $padroes]) {
            if (Texto::contemAlgum($normalizado, $padroes)) {
                return $pasta;
            }
        }

        return null;
    }

    /**
     * Palavras-chave usadas nos nomes de arquivos e pastas entregues ao TCM. "Relatório" não entra:
     * é genérico demais (relatórios do SIGA, por exemplo) e Relatórios Diversos já é o padrão.
     *
     * @return list<array{0: PastaDaPrestacao, 1: list<string>}>
     */
    private function regrasDeNome(): array
    {
        return [
            [PastaDaPrestacao::Raiz, ['/RECIBO\s*(?:DE\s*)?(?:ENTREGA\s*(?:DE\s*)?)?PRESTACAO/']],
            [PastaDaPrestacao::InexEDispensas, ['/\bINEX\b/', '/INEXIGIBILIDADE/', '/\bDISPENSAS?\b/']],
            [PastaDaPrestacao::Pregao, ['/\bPREGAO\b/']],
            [PastaDaPrestacao::ContratosEAditivos, ['/\bCONTRATOS?\b/', '/\bADITIVOS?\b/']],
            [PastaDaPrestacao::Siga, ['/\bSIGA\b/']],
        ];
    }

    /**
     * Títulos característicos no cabeçalho da primeira página. O contrato vem antes da
     * inexigibilidade porque contratos citam o processo de origem logo no preâmbulo.
     *
     * @return list<array{0: PastaDaPrestacao, 1: list<string>}>
     */
    private function regrasDeConteudo(): array
    {
        return [
            [PastaDaPrestacao::Raiz, ['/RECIBO DE ENTREGA DE PRESTACAO DE CONTAS/']],
            [PastaDaPrestacao::Siga, ['/SIGA - SISTEMA INTEGRADO DE GESTAO E AUDITORIA/']],
            [PastaDaPrestacao::ContratosEAditivos, ['/\b(?:TERMO ADITIVO|CONTRATO N[O.]?\W{0,3}\d)/']],
            [PastaDaPrestacao::InexEDispensas, ['/INEXIGIBILIDADE/', '/DISPENSA\W+(?:DE\W+)?LICITA/']],
            [PastaDaPrestacao::Pregao, ['/PREGAO (?:ELETRONICO|PRESENCIAL)/']],
        ];
    }
}
