<?php

namespace App\Services\Organizador;

use App\Enums\PastaDaPrestacao;
use App\Models\Organizacao;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fluxo completo: lê o envio, aplica OCR, monta processos e documentos, ajusta tamanhos e gera o zip.
 */
class OrganizadorDePrestacao
{
    public function __construct(
        private LeitorDeEntrada $leitor,
        private PreparadorDeArquivos $preparador,
        private MontadorDeProcessos $montadorDeProcessos,
        private MontadorDeAvulsos $montadorDeAvulsos,
        private AjustadorDeTamanho $ajustador,
        private EmpacotadorDeSaida $empacotador,
    ) {}

    public function organizar(Organizacao $organizacao): void
    {
        $trabalho = $organizacao->caminhoAbsoluto($organizacao->diretorioDeTrabalho());
        File::deleteDirectory($trabalho);

        try {
            $this->executar($organizacao, $trabalho);
        } finally {
            File::deleteDirectory($trabalho);
        }

        Storage::disk($organizacao->disco())->deleteDirectory($organizacao->diretorioDeEntrada());
    }

    private function executar(Organizacao $organizacao, string $trabalho): void
    {
        $entrada = $this->leitor->ler($organizacao->caminhoAbsoluto($organizacao->diretorioDeEntrada()), $trabalho.'/extraido');

        if ($entrada['pdfs'] === []) {
            throw new RuntimeException('Nenhum PDF encontrado nos arquivos enviados.');
        }

        $entidade = NomeDeArquivo::seguro($organizacao->entidade ?? $entrada['entidade'] ?? 'Prestação de contas');
        $preparo = $this->preparador->preparar($entrada['pdfs'], $trabalho.'/ocr');
        $montagem = $this->montadorDeProcessos->montar($preparo['arquivos']);
        $documentos = [...$montagem['processos'], ...$this->montadorDeAvulsos->montar($montagem['restantes'])];

        $raiz = $trabalho.'/saida/'.$entidade;
        $gerados = $this->gerar($documentos, $raiz, $trabalho.'/tmp');

        $zip = $organizacao->diretorio().'/'.$entidade.'.zip';
        $this->empacotador->empacotar($raiz, $organizacao->caminhoAbsoluto($zip));

        $organizacao->concluir(
            entidade: $entidade,
            arquivoSaida: $zip,
            arquivos: array_map(fn (ArquivoGerado $gerado) => $gerado->paraRegistro(), $gerados),
            avisos: [...$entrada['avisos'], ...$preparo['avisos'], ...$montagem['avisos']],
        );
    }

    /**
     * Grava os PDFs finais, em ordem de pasta e nome para que nomes repetidos sejam numerados sempre igual.
     *
     * @param  list<DocumentoDeSaida>  $documentos
     * @return list<ArquivoGerado>
     */
    private function gerar(array $documentos, string $raiz, string $temporario): array
    {
        usort($documentos, fn (DocumentoDeSaida $a, DocumentoDeSaida $b) => [$a->pasta->caminho(), $a->nome] <=> [$b->pasta->caminho(), $b->nome]);

        foreach (PastaDaPrestacao::sempreCriadas() as $pasta) {
            File::ensureDirectoryExists($raiz.'/'.$pasta->caminho());
        }

        $gerados = [];

        foreach ($documentos as $documento) {
            $destino = rtrim($raiz.'/'.$documento->pasta->caminho(), '/');
            array_push($gerados, ...$this->ajustador->gerar($documento, $destino, $temporario));
        }

        return $gerados;
    }
}
