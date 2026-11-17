<?php

namespace App\Services\Organizador;

use App\Enums\PastaDaPrestacao;

/**
 * Documentos que não são processo de pagamento: ganham a pasta certa e as partes
 * ("... - Parte 1", "... - Parte 2") são unificadas para depois serem redivididas pelo tamanho.
 */
class MontadorDeAvulsos
{
    public function __construct(private ClassificadorDeDocumento $classificador) {}

    /**
     * @param  list<ArquivoDeEntrada>  $arquivos
     * @return list<DocumentoDeSaida>
     */
    public function montar(array $arquivos): array
    {
        $grupos = [];

        foreach ($arquivos as $arquivo) {
            $pasta = $this->classificador->pasta($arquivo->caminhoRelativo, $arquivo->textoDaPrimeiraPagina);
            $chave = $pasta->value.'|'.mb_strtoupper(NomeDeArquivo::semParte($arquivo->nome()));
            $grupos[$chave]['pasta'] = $pasta;
            $grupos[$chave]['arquivos'][] = $arquivo;
        }

        return array_values(array_map(fn (array $grupo) => $this->documento($grupo['pasta'], $grupo['arquivos']), $grupos));
    }

    /**
     * @param  list<ArquivoDeEntrada>  $arquivos
     */
    private function documento(PastaDaPrestacao $pasta, array $arquivos): DocumentoDeSaida
    {
        if (count($arquivos) === 1) {
            return new DocumentoDeSaida(
                pasta: $pasta,
                nome: NomeDeArquivo::seguro($arquivos[0]->nome()),
                paginas: $arquivos[0]->referencias(),
                avisos: $arquivos[0]->avisos,
                arquivoInteiro: $arquivos[0]->caminho,
            );
        }

        usort($arquivos, fn (ArquivoDeEntrada $a, ArquivoDeEntrada $b) => (NomeDeArquivo::parte($a->nome()) ?? 0) <=> (NomeDeArquivo::parte($b->nome()) ?? 0));

        $nomes = array_map(fn (ArquivoDeEntrada $arquivo) => $arquivo->nome(), $arquivos);

        return new DocumentoDeSaida(
            pasta: $pasta,
            nome: NomeDeArquivo::seguro(NomeDeArquivo::semParte($arquivos[0]->nome())),
            paginas: array_merge(...array_map(fn (ArquivoDeEntrada $arquivo) => $arquivo->referencias(), $arquivos)),
            avisos: ['Unificado de: '.implode(', ', $nomes).'.', ...array_merge(...array_map(fn (ArquivoDeEntrada $arquivo) => $arquivo->avisos, $arquivos))],
        );
    }
}
