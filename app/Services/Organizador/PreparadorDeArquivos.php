<?php

namespace App\Services\Organizador;

use App\Exceptions\FerramentaPdfFalhou;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Deixa cada PDF pronto para a montagem: descarta repetidos, aplica OCR e classifica as páginas.
 */
class PreparadorDeArquivos
{
    public function __construct(
        private FerramentasPdf $pdf,
        private ClassificadorDePagina $classificador,
    ) {}

    /**
     * @param  list<array{caminho: string, relativo: string}>  $pdfs
     * @return array{arquivos: list<ArquivoDeEntrada>, avisos: list<string>}
     */
    public function preparar(array $pdfs, string $diretorioDeOcr): array
    {
        File::ensureDirectoryExists($diretorioDeOcr);

        /** @var array<string, string> $vistos hash => caminho relativo */
        $vistos = [];
        $arquivos = [];
        $avisos = [];

        foreach ($pdfs as $indice => $pdf) {
            $hash = (string) hash_file('sha256', $pdf['caminho']);

            if (isset($vistos[$hash])) {
                $avisos[] = "\"{$pdf['relativo']}\" ignorado: é idêntico a \"{$vistos[$hash]}\".";

                continue;
            }

            $vistos[$hash] = $pdf['relativo'];

            try {
                $arquivos[] = $this->prepararArquivo($pdf, $hash, "{$diretorioDeOcr}/{$indice}.pdf");
            } catch (FerramentaPdfFalhou $erro) {
                $avisos[] = "\"{$pdf['relativo']}\" ignorado: não foi possível ler o PDF ({$this->resumo($erro)}).";
            }
        }

        return ['arquivos' => $arquivos, 'avisos' => $avisos];
    }

    /**
     * @param  array{caminho: string, relativo: string}  $pdf
     */
    private function prepararArquivo(array $pdf, string $hash, string $destinoDoOcr): ArquivoDeEntrada
    {
        ['caminho' => $caminho, 'avisos' => $avisos] = $this->comOcr($pdf['caminho'], $destinoDoOcr);
        $textos = $this->textos($caminho);

        return new ArquivoDeEntrada(
            caminho: $caminho,
            caminhoRelativo: $pdf['relativo'],
            paginas: array_map(fn (string $texto) => $this->classificador->classificar($texto), $textos),
            textoDaPrimeiraPagina: $textos[0] ?? '',
            hash: $hash,
            avisos: [...$avisos, ...$this->avisosDePaginasSemTexto($pdf['relativo'], $textos)],
        );
    }

    /**
     * Refaz o OCR; se não der (assinatura digital, PDF protegido), segue com o original.
     *
     * @return array{caminho: string, avisos: list<string>}
     */
    private function comOcr(string $origem, string $destino): array
    {
        if (! config('organizador.ocr')) {
            return ['caminho' => $origem, 'avisos' => []];
        }

        try {
            $this->pdf->aplicarOcr($origem, $destino);
        } catch (FerramentaPdfFalhou $erro) {
            return ['caminho' => $origem, 'avisos' => ['OCR não aplicado, mantido como veio ('.$this->resumo($erro).').']];
        }

        return ['caminho' => $destino, 'avisos' => []];
    }

    /**
     * Texto de cada página, uma entrada por página do PDF.
     *
     * @return list<string>
     */
    private function textos(string $caminho): array
    {
        $paginas = $this->pdf->contarPaginas($caminho);
        $textos = array_slice($this->pdf->textoDasPaginas($caminho), 0, $paginas);

        return array_pad($textos, $paginas, '');
    }

    /**
     * @param  list<string>  $textos
     * @return list<string>
     */
    private function avisosDePaginasSemTexto(string $relativo, array $textos): array
    {
        $minimo = (int) config('organizador.minimo_caracteres_texto');
        $semTexto = array_keys(array_filter($textos, fn (string $texto) => mb_strlen(trim($texto)) < $minimo));

        if ($semTexto === []) {
            return [];
        }

        $paginas = implode(', ', array_map(fn (int $indice) => $indice + 1, $semTexto));

        return ["\"{$relativo}\": páginas sem texto pesquisável ({$paginas}); confira se estão em branco."];
    }

    private function resumo(FerramentaPdfFalhou $erro): string
    {
        return Str::limit(Str::squish($erro->getMessage()), 160);
    }
}
