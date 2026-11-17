<?php

namespace App\Enums;

/**
 * Peça de um processo de pagamento, na ordem exigida pela planilha de formatação do TCM.
 *
 * Orçamentário: Ordem de Pagamento → Liquidação → Empenho → Sub-empenho → NF/Folha/Diárias → Comprovante.
 * Extra-orçamentário: Capa → Guia de Despesa → Comprovante.
 */
enum TipoDePagina: string
{
    case OrdemDePagamento = 'ordem_de_pagamento';

    case OrdemDePagamentoExtra = 'ordem_de_pagamento_extra';

    case NotaDeLiquidacao = 'nota_de_liquidacao';

    case NotaDeEmpenho = 'nota_de_empenho';

    case NotaDeSubEmpenho = 'nota_de_sub_empenho';

    case GuiaDeDespesa = 'guia_de_despesa';

    case Documento = 'documento';

    case Comprovante = 'comprovante';

    /**
     * Posição da peça dentro do PDF do processo.
     */
    public function ordem(): int
    {
        return match ($this) {
            self::OrdemDePagamento, self::OrdemDePagamentoExtra => 1,
            self::NotaDeLiquidacao => 2,
            self::NotaDeEmpenho => 3,
            self::NotaDeSubEmpenho => 4,
            self::GuiaDeDespesa => 5,
            self::Documento => 6,
            self::Comprovante => 7,
        };
    }

    /**
     * Rótulo exibido nos avisos.
     */
    public function label(): string
    {
        return match ($this) {
            self::OrdemDePagamento => 'Ordem de Pagamento',
            self::OrdemDePagamentoExtra => 'Capa de Pagamento (extra)',
            self::NotaDeLiquidacao => 'Nota de Liquidação',
            self::NotaDeEmpenho => 'Nota de Empenho',
            self::NotaDeSubEmpenho => 'Nota de Sub-empenho',
            self::GuiaDeDespesa => 'Guia de Despesa',
            self::Documento => 'Documento fiscal/folha/diárias',
            self::Comprovante => 'Comprovante de Pagamento',
        };
    }

    /**
     * Capa que abre um processo de pagamento e traz o número dele.
     */
    public function isCapa(): bool
    {
        return $this === self::OrdemDePagamento || $this === self::OrdemDePagamentoExtra;
    }

    /**
     * Capa e notas emitidas pelo sistema contábil (título sempre no cabeçalho da página).
     */
    public function isPecaContabil(): bool
    {
        return match ($this) {
            self::OrdemDePagamento, self::OrdemDePagamentoExtra, self::NotaDeLiquidacao, self::NotaDeEmpenho, self::NotaDeSubEmpenho => true,
            default => false,
        };
    }
}
