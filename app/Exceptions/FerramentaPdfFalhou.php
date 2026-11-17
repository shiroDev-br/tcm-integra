<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Uma das ferramentas de linha de comando de PDF (qpdf, pdftotext, gs, ocrmypdf) falhou.
 */
class FerramentaPdfFalhou extends RuntimeException
{
    public static function ao(string $acao, string $arquivo, string $saida): self
    {
        return new self(sprintf('Falha ao %s "%s": %s', $acao, basename($arquivo), trim($saida)));
    }
}
