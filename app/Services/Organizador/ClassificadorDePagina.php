<?php

namespace App\Services\Organizador;

use App\Enums\TipoDePagina;

/**
 * Reconhece a peça do processo de pagamento pelo texto da página.
 *
 * As regras toleram os erros mais comuns de OCR ("NOTA OE EMPENHO", "LIOUIDAÇÃO").
 */
class ClassificadorDePagina
{
    private const DE = '[DO0]E';

    private const EMPENHO = '/EMPENHO\W{0,3}(\d{10})\s*\/\s*(\d{4})/';

    /** "Nº SUB-EMPENHO: 8"; exige o "Nº" para não confundir com "Data do Sub-Empenho: 20/01". */
    private const SUB_EMPENHO = '/\bN[O0]?\W{0,3}SUB.?EMPENHO\W{0,3}(\d{1,6})(?![\d\/.,])/';

    private const FORNECEDOR = '/NOME\W{0,2}(?:\d{1,5}\s*[-.]\s*)?(.+?)\s+(?:ENDERECO|TIPO PESSOA|CNPJ|COMPLEMENTO|BAIRRO)\b/';

    public function classificar(string $texto): PaginaClassificada
    {
        $normalizado = Texto::normalizar($texto);
        $tipo = $this->tipo($normalizado);

        if ($tipo === TipoDePagina::Documento) {
            return new PaginaClassificada($tipo, folha: $this->varianteDaFolha($normalizado));
        }

        [$empenho, $subEmpenho] = $this->empenho($normalizado);

        if (! $tipo->isCapa()) {
            return new PaginaClassificada($tipo, empenho: $empenho, subEmpenho: $subEmpenho);
        }

        return new PaginaClassificada(
            tipo: $tipo,
            numeroProcesso: NomeDeArquivo::numeroDoProcesso($normalizado),
            empenho: $empenho,
            subEmpenho: $subEmpenho,
            fornecedor: $this->primeiraCaptura(self::FORNECEDOR, $normalizado),
        );
    }

    private function tipo(string $texto): TipoDePagina
    {
        // Capa e notas trazem o título no cabeçalho; contratos e regimentos citam "nota de empenho" no corpo.
        $cabecalho = Texto::cabecalho($texto);

        foreach ($this->regras() as [$tipo, $padroes]) {
            if (Texto::contemAlgum($tipo->isPecaContabil() ? $cabecalho : $texto, $padroes)) {
                return $tipo;
            }
        }

        return TipoDePagina::Documento;
    }

    /**
     * Regras na ordem de prioridade: a capa extra antes da capa comum, as notas antes do comprovante.
     *
     * @return list<array{0: TipoDePagina, 1: list<string>}>
     */
    private function regras(): array
    {
        $de = self::DE;

        return [
            // "LISTAGEM DE ORDEM DE PAGAMENTO" é relatório, não capa.
            [TipoDePagina::OrdemDePagamentoExtra, ["/(?<!LISTAGEM {$de} )ORDEM {$de} PAGAMENTO\W*EXTRA/", '/DESPESA EXTRA\W{0,3}\d+\s*\/\s*\d{4}/']],
            [TipoDePagina::OrdemDePagamento, ["/(?<!LISTAGEM {$de} )ORDEM {$de} PAGAMENTO\b/"]],
            [TipoDePagina::NotaDeLiquidacao, ["/NOTA {$de} LI[QO0]UI[DO0]A/"]],
            [TipoDePagina::NotaDeSubEmpenho, ["/NOTA {$de} SUB.?EMPENH/"]],
            [TipoDePagina::NotaDeEmpenho, ["/NOTA {$de} EMPENH/"]],
            [TipoDePagina::Comprovante, [
                '/COMPROVANTE (?:DE )?(?:PAGAMENTO|TRANSF|TRANSACAO|TED|PIX|DOC)/',
                '/\bTE[VD] ENVIADA/',
                '/INTERNET BANKING/',
                '/GERENCIADOR FINANCEIRO/',
            ]],
            [TipoDePagina::GuiaDeDespesa, [
                "/DOCUMENTO {$de} ARRECADACAO/",
                '/GUIA (?:DA PREVIDENCIA|DE RECOLHIMENTO)/',
                '/\bDARF\b/',
            ]],
        ];
    }

    /**
     * Folha de pagamento: analítica lista cada servidor (com data de admissão), sintética só os totais.
     */
    private function varianteDaFolha(string $texto): ?VarianteDaFolha
    {
        if (! str_contains($texto, 'FOLHA DE PAGAMENTO')) {
            return null;
        }

        if (str_contains($texto, 'ADMISS')) {
            return VarianteDaFolha::Analitica;
        }

        return VarianteDaFolha::Sintetica;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function empenho(string $texto): array
    {
        if (preg_match(self::EMPENHO, $texto, $empenho) !== 1) {
            return [null, null];
        }

        return [$empenho[1].'/'.$empenho[2], $this->primeiraCaptura(self::SUB_EMPENHO, $texto)];
    }

    private function primeiraCaptura(string $padrao, string $texto): ?string
    {
        if (preg_match($padrao, $texto, $captura) !== 1) {
            return null;
        }

        return trim($captura[1]);
    }
}
