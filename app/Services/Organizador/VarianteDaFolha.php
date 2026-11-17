<?php

namespace App\Services\Organizador;

use App\Enums\PastaDaPrestacao;

/**
 * Folha de pagamento entregue em duas versões: analítica (por servidor) e sintética (totais).
 */
enum VarianteDaFolha: string
{
    case Analitica = 'ANALITICA';

    case Sintetica = 'SINTETICA';

    public function pasta(): PastaDaPrestacao
    {
        return match ($this) {
            self::Analitica => PastaDaPrestacao::FolhaAnalitica,
            self::Sintetica => PastaDaPrestacao::FolhaSintetica,
        };
    }

    /**
     * Variante citada no nome do arquivo ("... FOLHA ANALITICA.pdf"), se houver.
     */
    public static function doNomeDoArquivo(string $caminho): ?self
    {
        $nome = Texto::normalizar($caminho);

        if (str_contains($nome, 'ANALIT')) {
            return self::Analitica;
        }

        if (str_contains($nome, 'SINTET')) {
            return self::Sintetica;
        }

        return null;
    }
}
