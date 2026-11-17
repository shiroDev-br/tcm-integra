<?php

namespace App\Services\Organizador;

use Illuminate\Support\Facades\File;

/**
 * Grava o PDF final de um documento respeitando os limites do TCM:
 * comprime quando passa de 500KB por página ou 5000KB por arquivo e, se ainda
 * não couber, divide em "Parte 1", "Parte 2"...
 */
class AjustadorDeTamanho
{
    public function __construct(private FerramentasPdf $pdf) {}

    /**
     * @return list<ArquivoGerado>
     */
    public function gerar(DocumentoDeSaida $documento, string $diretorioDestino, string $diretorioTemporario): array
    {
        File::ensureDirectoryExists($diretorioDestino);
        File::ensureDirectoryExists($diretorioTemporario);
        File::cleanDirectory($diretorioTemporario);

        $montado = $this->montar($documento, $diretorioTemporario.'/montado.pdf');

        ['arquivo' => $arquivo, 'tamanhos' => $tamanhos] = $this->comprimirSePreciso($montado, $diretorioTemporario);
        $avisos = [...$documento->avisos, ...$this->avisosDePagina($tamanhos)];

        if (filesize($arquivo) <= $this->limiteDoArquivo()) {
            $destino = $this->caminhoDisponivel($diretorioDestino, $documento->nome);
            File::move($arquivo, $destino);

            return [new ArquivoGerado($documento->pasta, basename($destino), count($tamanhos), (int) filesize($destino), $avisos)];
        }

        return $this->dividir($documento, $arquivo, $tamanhos, $diretorioDestino, $avisos);
    }

    private function montar(DocumentoDeSaida $documento, string $destino): string
    {
        if ($documento->arquivoInteiro !== null) {
            File::copy($documento->arquivoInteiro, $destino);

            return $destino;
        }

        $this->pdf->juntarPaginas($documento->paginas, $destino);

        return $destino;
    }

    /**
     * Tenta cada nível de compressão até caber, ficando sempre com o menor resultado.
     *
     * @return array{arquivo: string, tamanhos: list<int>}
     */
    private function comprimirSePreciso(string $pdf, string $diretorioTemporario): array
    {
        $melhor = ['arquivo' => $pdf, 'tamanhos' => $this->pdf->tamanhoDasPaginas($pdf, $diretorioTemporario.'/paginas')];

        foreach (config('organizador.compressao') as $indice => $nivel) {
            if (! $this->excedeLimites($melhor['arquivo'], $melhor['tamanhos'])) {
                break;
            }

            $comprimido = $diretorioTemporario."/comprimido-{$indice}.pdf";
            $this->pdf->comprimir($pdf, $comprimido, $nivel['preset'], $nivel['dpi']);

            if (filesize($comprimido) >= filesize($melhor['arquivo'])) {
                continue;
            }

            $melhor = ['arquivo' => $comprimido, 'tamanhos' => $this->pdf->tamanhoDasPaginas($comprimido, $diretorioTemporario.'/paginas')];
        }

        return $melhor;
    }

    /**
     * Divide em partes pela soma do tamanho das páginas (estimativa conservadora).
     *
     * @param  list<int>  $tamanhos
     * @param  list<string>  $avisos
     * @return list<ArquivoGerado>
     */
    private function dividir(DocumentoDeSaida $documento, string $arquivo, array $tamanhos, string $diretorioDestino, array $avisos): array
    {
        $partes = $this->agruparPaginasEmPartes($tamanhos);
        $avisos[] = 'Dividido em '.count($partes).' partes para respeitar o limite de '.config('organizador.limite_arquivo_kb').'KB.';
        $gerados = [];

        foreach ($partes as $indice => $paginas) {
            $destino = $this->caminhoDisponivel($diretorioDestino, NomeDeArquivo::comParte($documento->nome, $indice + 1));
            $this->pdf->juntarPaginas(array_map(fn (int $pagina) => ['arquivo' => $arquivo, 'pagina' => $pagina], $paginas), $destino);

            $gerados[] = new ArquivoGerado($documento->pasta, basename($destino), count($paginas), (int) filesize($destino), $avisos);
        }

        return $gerados;
    }

    /**
     * @param  list<int>  $tamanhos
     * @return list<list<int>> Números de página (a partir de 1) de cada parte.
     */
    private function agruparPaginasEmPartes(array $tamanhos): array
    {
        $partes = [[]];
        $acumulado = 0;

        foreach ($tamanhos as $indice => $tamanho) {
            $atual = array_key_last($partes);

            if ($partes[$atual] !== [] && $acumulado + $tamanho > $this->limiteDoArquivo()) {
                $partes[] = [];
                $atual++;
                $acumulado = 0;
            }

            $partes[$atual][] = $indice + 1;
            $acumulado += $tamanho;
        }

        return $partes;
    }

    /**
     * @param  list<int>  $tamanhos
     */
    private function excedeLimites(string $pdf, array $tamanhos): bool
    {
        if (filesize($pdf) > $this->limiteDoArquivo()) {
            return true;
        }

        return max([0, ...$tamanhos]) > $this->limiteDaPagina();
    }

    /**
     * @param  list<int>  $tamanhos
     * @return list<string>
     */
    private function avisosDePagina(array $tamanhos): array
    {
        $acima = array_filter($tamanhos, fn (int $tamanho) => $tamanho > $this->limiteDaPagina());

        return array_map(
            fn (int $tamanho, int $indice) => sprintf('Página %d com %dKB, acima do limite de %dKB mesmo após compressão.', $indice + 1, (int) ceil($tamanho / 1024), config('organizador.limite_pagina_kb')),
            $acima,
            array_keys($acima),
        );
    }

    /**
     * Caminho livre para o nome, acrescentando " (2)", " (3)"... quando já existe.
     */
    private function caminhoDisponivel(string $diretorio, string $nome): string
    {
        $caminho = "{$diretorio}/{$nome}.pdf";

        for ($copia = 2; File::exists($caminho); $copia++) {
            $caminho = "{$diretorio}/{$nome} ({$copia}).pdf";
        }

        return $caminho;
    }

    private function limiteDoArquivo(): int
    {
        return (int) config('organizador.limite_arquivo_kb') * 1024;
    }

    private function limiteDaPagina(): int
    {
        return (int) config('organizador.limite_pagina_kb') * 1024;
    }
}
