<?php

namespace App\Enums;

/**
 * Pastas do zip organizado da prestação de contas.
 */
enum PastaDaPrestacao: string
{
    case Raiz = 'raiz';

    case ContratosEAditivos = 'contratos_e_aditivos';

    case Extra = 'extra';

    case FolhaAnalitica = 'folha_analitica';

    case FolhaSintetica = 'folha_sintetica';

    case ProcessoPagamento = 'processo_pagamento';

    case RelatoriosDiversos = 'relatorios_diversos';

    case Siga = 'siga';

    case InexEDispensas = 'inex_e_dispensas';

    case Pregao = 'pregao';

    /**
     * Caminho da pasta dentro da pasta da entidade; vazio para a raiz.
     */
    public function caminho(): string
    {
        return match ($this) {
            self::Raiz => '',
            self::ContratosEAditivos => 'CONTRATOS E ADITIVOS',
            self::Extra => 'EXTRA',
            self::FolhaAnalitica => 'FOLHA/ANALÍTICA',
            self::FolhaSintetica => 'FOLHA/SINTÉTICA',
            self::ProcessoPagamento => 'PROCESSO PAGAMENTO',
            self::RelatoriosDiversos => 'RELATÓRIOS DIVERSOS',
            self::Siga => 'SIGA',
            self::InexEDispensas => 'LICITAÇÃO/INEX e DISPENSAS',
            self::Pregao => 'LICITAÇÃO/PREGÃO',
        };
    }

    /**
     * Rótulo exibido na interface.
     */
    public function label(): string
    {
        if ($this === self::Raiz) {
            return 'Raiz';
        }

        return $this->caminho();
    }

    /**
     * Pastas criadas no zip mesmo vazias, como no modelo de entrega.
     *
     * @return list<self>
     */
    public static function sempreCriadas(): array
    {
        return [self::Pregao];
    }
}
