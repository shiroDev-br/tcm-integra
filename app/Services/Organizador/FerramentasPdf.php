<?php

namespace App\Services\Organizador;

use App\Exceptions\FerramentaPdfFalhou;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Acesso às ferramentas de linha de comando de PDF.
 *
 * Os comandos são passados como lista (sem shell), então nenhum nome de arquivo é interpretado.
 */
class FerramentasPdf
{
    /** Separador de páginas na saída do pdftotext. */
    private const QUEBRA_DE_PAGINA = "\f";

    public function contarPaginas(string $pdf): int
    {
        $resultado = $this->executar([$this->binario('qpdf'), '--show-npages', $pdf], 'contar as páginas de', $pdf);

        return (int) trim($resultado->output());
    }

    /**
     * Texto de cada página, na ordem do arquivo.
     *
     * @return list<string>
     */
    public function textoDasPaginas(string $pdf): array
    {
        $resultado = $this->executar([$this->binario('pdftotext'), '-q', $pdf, '-'], 'ler o texto de', $pdf);

        $paginas = explode(self::QUEBRA_DE_PAGINA, $resultado->output());

        // O pdftotext termina cada página com \f, então o último pedaço é sempre vazio.
        array_pop($paginas);

        return $paginas;
    }

    /**
     * Refaz o OCR das páginas digitalizadas (texto de OCR antigo é substituído; texto nativo é mantido).
     */
    public function aplicarOcr(string $origem, string $destino): void
    {
        $this->executar([
            $this->binario('ocrmypdf'),
            '--redo-ocr',
            '--output-type', 'pdf',
            '--language', (string) config('organizador.idioma_ocr'),
            '--quiet',
            $origem,
            $destino,
        ], 'aplicar OCR em', $origem);
    }

    /**
     * Monta um PDF com as páginas indicadas, na ordem dada.
     *
     * @param  list<array{arquivo: string, pagina: int}>  $paginas
     */
    public function juntarPaginas(array $paginas, string $destino): void
    {
        $argumentos = [$this->binario('qpdf'), '--empty', '--pages'];

        foreach ($this->agruparEmIntervalos($paginas) as $intervalo) {
            $argumentos[] = $intervalo['arquivo'];
            $argumentos[] = $intervalo['paginas'];
        }

        $argumentos[] = '--';
        $argumentos[] = $destino;

        $this->executar($argumentos, 'montar', $destino);
    }

    /**
     * Recomprime imagens com o Ghostscript, mantendo a camada de texto.
     */
    public function comprimir(string $origem, string $destino, string $preset, int $dpi): void
    {
        $this->executar([
            $this->binario('ghostscript'),
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.5',
            '-dPDFSETTINGS='.$preset,
            '-dDownsampleColorImages=true',
            '-dDownsampleGrayImages=true',
            '-dDownsampleMonoImages=true',
            '-dColorImageResolution='.$dpi,
            '-dGrayImageResolution='.$dpi,
            '-dMonoImageResolution='.($dpi * 2),
            '-dNOPAUSE',
            '-dBATCH',
            '-dQUIET',
            '-dSAFER',
            '-sOutputFile='.$destino,
            $origem,
        ], 'comprimir', $origem);
    }

    /**
     * Tamanho em bytes de cada página isolada, na ordem do arquivo.
     *
     * @return list<int>
     */
    public function tamanhoDasPaginas(string $pdf, string $diretorioTemporario): array
    {
        File::ensureDirectoryExists($diretorioTemporario);
        File::cleanDirectory($diretorioTemporario);

        $this->executar([
            $this->binario('qpdf'), '--split-pages', $pdf, $diretorioTemporario.'/pagina-%d.pdf',
        ], 'separar as páginas de', $pdf);

        $arquivos = File::files($diretorioTemporario);

        // qpdf completa o número com zeros à esquerda, então a ordem alfabética é a ordem das páginas.
        usort($arquivos, fn ($a, $b) => strcmp($a->getFilename(), $b->getFilename()));

        $tamanhos = array_map(fn ($arquivo) => (int) $arquivo->getSize(), $arquivos);

        File::deleteDirectory($diretorioTemporario);

        return $tamanhos;
    }

    /**
     * Junta páginas consecutivas do mesmo arquivo em intervalos ("1-4,7") para encurtar o comando.
     *
     * @param  list<array{arquivo: string, pagina: int}>  $paginas
     * @return list<array{arquivo: string, paginas: string}>
     */
    private function agruparEmIntervalos(array $paginas): array
    {
        $grupos = [];

        foreach ($paginas as $pagina) {
            $ultimo = array_key_last($grupos);

            if ($ultimo === null || $grupos[$ultimo]['arquivo'] !== $pagina['arquivo']) {
                $grupos[] = ['arquivo' => $pagina['arquivo'], 'faixas' => [[$pagina['pagina'], $pagina['pagina']]]];

                continue;
            }

            $faixa = array_key_last($grupos[$ultimo]['faixas']);

            if ($grupos[$ultimo]['faixas'][$faixa][1] + 1 === $pagina['pagina']) {
                $grupos[$ultimo]['faixas'][$faixa][1] = $pagina['pagina'];

                continue;
            }

            $grupos[$ultimo]['faixas'][] = [$pagina['pagina'], $pagina['pagina']];
        }

        return array_map(fn (array $grupo) => [
            'arquivo' => $grupo['arquivo'],
            'paginas' => implode(',', array_map(
                fn (array $faixa) => $faixa[0] === $faixa[1] ? (string) $faixa[0] : $faixa[0].'-'.$faixa[1],
                $grupo['faixas'],
            )),
        ], $grupos);
    }

    /**
     * @param  list<string>  $comando
     */
    private function executar(array $comando, string $acao, string $arquivo): ProcessResult
    {
        $resultado = Process::timeout((int) config('organizador.timeout_ferramenta'))->run($comando);

        // qpdf devolve 3 quando conclui com avisos (PDF levemente corrompido mas legível).
        if ($resultado->successful() || ($comando[0] === $this->binario('qpdf') && $resultado->exitCode() === 3)) {
            return $resultado;
        }

        throw FerramentaPdfFalhou::ao($acao, $arquivo, $resultado->errorOutput() ?: $resultado->output());
    }

    private function binario(string $nome): string
    {
        return (string) config('organizador.binarios.'.$nome);
    }
}
