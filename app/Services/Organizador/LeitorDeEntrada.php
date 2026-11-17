<?php

namespace App\Services\Organizador;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Lê os arquivos enviados (zips e PDFs soltos) e devolve os PDFs com o caminho original de cada um.
 */
class LeitorDeEntrada
{
    /**
     * @return array{pdfs: list<array{caminho: string, relativo: string}>, entidade: string|null, avisos: list<string>}
     */
    public function ler(string $diretorioDeUploads, string $diretorioDeExtracao): array
    {
        File::ensureDirectoryExists($diretorioDeExtracao);

        $pdfs = [];
        $avisos = [];
        $zips = [];

        foreach ($this->arquivosOrdenados($diretorioDeUploads) as $arquivo) {
            $extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));

            if ($extensao === 'pdf') {
                $pdfs[] = ['caminho' => $arquivo, 'relativo' => basename($arquivo)];

                continue;
            }

            if ($extensao !== 'zip') {
                $avisos[] = '"'.basename($arquivo).'" ignorado: envie PDFs ou zips.';

                continue;
            }

            $zips[] = $arquivo;
            $extraidos = $this->extrairZip($arquivo, $diretorioDeExtracao.'/'.count($zips), $avisos);
            array_push($pdfs, ...$extraidos);
        }

        return [
            'pdfs' => $this->semPastaRaizComum($pdfs),
            'entidade' => $this->entidade($zips, $pdfs),
            'avisos' => $avisos,
        ];
    }

    /**
     * @return list<string>
     */
    private function arquivosOrdenados(string $diretorio): array
    {
        $arquivos = array_map(fn ($arquivo) => $arquivo->getPathname(), File::files($diretorio));
        sort($arquivos, SORT_NATURAL | SORT_FLAG_CASE);

        return $arquivos;
    }

    /**
     * Extrai só os PDFs, recusando caminhos que saiam da pasta de destino (zip-slip).
     *
     * @param  list<string>  $avisos
     * @return list<array{caminho: string, relativo: string}>
     */
    private function extrairZip(string $zipPath, string $destino, array &$avisos): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            $avisos[] = '"'.basename($zipPath).'" não é um zip válido e foi ignorado.';

            return [];
        }

        $limite = (int) config('organizador.limite_extraido_mb') * 1024 * 1024;
        $extraido = 0;
        $pdfs = [];

        for ($indice = 0; $indice < $zip->numFiles; $indice++) {
            $relativo = $this->caminhoSeguro($this->nomeEmUtf8($zip, $indice));

            if ($relativo === null || ! $this->isPdf($relativo)) {
                continue;
            }

            $caminho = $destino.'/'.$relativo;
            File::ensureDirectoryExists(dirname($caminho));

            // Conta o que foi de fato descompactado: o tamanho declarado no zip pode mentir.
            $extraido += $this->copiarEntrada($zip, $indice, $caminho, $limite - $extraido + 1);

            if ($extraido > $limite) {
                $zip->close();

                throw new RuntimeException('O conteúdo descompactado passa de '.config('organizador.limite_extraido_mb').'MB.');
            }

            $pdfs[] = ['caminho' => $caminho, 'relativo' => $relativo];
        }

        $zip->close();

        if ($pdfs === []) {
            $avisos[] = '"'.basename($zipPath).'" não tem nenhum PDF.';
        }

        return $pdfs;
    }

    /**
     * Copia a entrada para o disco, até o máximo de bytes, e devolve quantos bytes foram copiados.
     */
    private function copiarEntrada(ZipArchive $zip, int $indice, string $caminho, int $maximoDeBytes): int
    {
        $origem = $zip->getStreamIndex($indice);
        $destino = fopen($caminho, 'wb');

        if ($origem === false || $destino === false) {
            throw new RuntimeException('Não foi possível extrair "'.basename($caminho).'" do zip.');
        }

        $copiados = (int) stream_copy_to_stream($origem, $destino, $maximoDeBytes);

        fclose($origem);
        fclose($destino);

        return $copiados;
    }

    /**
     * Zips do Windows gravam o nome em CP437/CP850 sem marcar UTF-8.
     */
    private function nomeEmUtf8(ZipArchive $zip, int $indice): string
    {
        $nome = (string) $zip->getNameIndex($indice, ZipArchive::FL_ENC_RAW);

        if (mb_check_encoding($nome, 'UTF-8')) {
            return $nome;
        }

        return (string) mb_convert_encoding($nome, 'UTF-8', 'CP850');
    }

    /**
     * Caminho relativo limpo, ou null para entradas a ignorar (pastas, lixo do macOS, "..").
     */
    private function caminhoSeguro(string $nome): ?string
    {
        $segmentos = array_values(array_filter(explode('/', str_replace('\\', '/', $nome)), fn (string $segmento) => $segmento !== '' && $segmento !== '.'));

        if ($segmentos === [] || str_ends_with($nome, '/')) {
            return null;
        }

        foreach ($segmentos as $segmento) {
            if ($segmento === '..' || $segmento === '__MACOSX' || str_starts_with($segmento, '.')) {
                return null;
            }
        }

        return implode('/', array_map(fn (string $segmento) => NomeDeArquivo::seguro($segmento), $segmentos));
    }

    private function isPdf(string $caminho): bool
    {
        return strtolower(pathinfo($caminho, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Quando tudo está dentro de uma única pasta (a da entidade), ela sai do caminho relativo.
     *
     * @param  list<array{caminho: string, relativo: string}>  $pdfs
     * @return list<array{caminho: string, relativo: string}>
     */
    private function semPastaRaizComum(array $pdfs): array
    {
        $raiz = $this->pastaRaizComum($pdfs);

        if ($raiz === null) {
            return $pdfs;
        }

        return array_map(fn (array $pdf) => [
            'caminho' => $pdf['caminho'],
            'relativo' => substr($pdf['relativo'], strlen($raiz) + 1),
        ], $pdfs);
    }

    /**
     * @param  list<array{caminho: string, relativo: string}>  $pdfs
     */
    private function pastaRaizComum(array $pdfs): ?string
    {
        $raizes = array_values(array_unique(array_map(fn (array $pdf) => Str::before($pdf['relativo'], '/'), $pdfs)));

        // Str::before devolve o caminho inteiro quando não há "/": arquivo solto na raiz do envio.
        if (count($raizes) !== 1 || ! str_contains($pdfs[0]['relativo'], '/')) {
            return null;
        }

        return $raizes[0];
    }

    /**
     * Nome da entidade: a pasta raiz do zip, senão o nome do zip.
     *
     * @param  list<string>  $zips
     * @param  list<array{caminho: string, relativo: string}>  $pdfs
     */
    private function entidade(array $zips, array $pdfs): ?string
    {
        $raiz = $this->pastaRaizComum($pdfs);

        if ($raiz !== null) {
            return $raiz;
        }

        if (count($zips) !== 1) {
            return null;
        }

        return pathinfo($zips[0], PATHINFO_FILENAME);
    }
}
