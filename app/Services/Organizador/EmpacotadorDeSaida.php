<?php

namespace App\Services\Organizador;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Compacta a pasta da entidade (com as subpastas, inclusive vazias) em um zip.
 */
class EmpacotadorDeSaida
{
    public function empacotar(string $diretorioDaEntidade, string $zipDeDestino): void
    {
        File::ensureDirectoryExists(dirname($zipDeDestino));
        File::delete($zipDeDestino);

        $zip = new ZipArchive;

        if ($zip->open($zipDeDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o zip de saída.');
        }

        $raiz = basename($diretorioDaEntidade);
        $zip->addEmptyDir($raiz);

        $itens = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($diretorioDaEntidade, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($itens as $item) {
            $noZip = $raiz.'/'.substr($item->getPathname(), strlen($diretorioDaEntidade) + 1);

            if ($item->isDir()) {
                $zip->addEmptyDir($noZip);

                continue;
            }

            $zip->addFile($item->getPathname(), $noZip);
        }

        $zip->close();
    }
}
