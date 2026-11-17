<?php

namespace App\Services\Organizador;

use App\Enums\TipoDePagina;

/**
 * Reúne as páginas dos processos de pagamento espalhadas pelos arquivos enviados.
 *
 * Cada capa (Ordem de Pagamento) abre um processo; as páginas seguintes pertencem a ele até a
 * próxima capa. Processos com o mesmo número são unificados. Arquivos sem capa entram no processo
 * indicado pelo número no nome do arquivo ou pelo empenho das notas.
 */
class MontadorDeProcessos
{
    /**
     * @param  list<ArquivoDeEntrada>  $arquivos
     * @return array{processos: list<DocumentoDeSaida>, restantes: list<ArquivoDeEntrada>, avisos: list<string>}
     */
    public function montar(array $arquivos): array
    {
        /** @var array<string, ProcessoEmMontagem> $processos */
        $processos = [];
        $semCapa = [];

        foreach ($arquivos as $arquivo) {
            if (! $arquivo->possuiCapa()) {
                $semCapa[] = $arquivo;

                continue;
            }

            foreach ($this->segmentar($arquivo) as $indice => $segmento) {
                $chave = $this->chave($segmento['numero'], $segmento['variante'], $arquivo, $indice);
                $processos[$chave] ??= new ProcessoEmMontagem($segmento['numero'], $segmento['variante']);
                $processos[$chave]->adicionar($arquivo->nome(), $segmento['paginas'], $arquivo->avisos);
            }
        }

        $restantes = [];
        $avisos = [];

        foreach ($semCapa as $arquivo) {
            $processo = $this->processoDoArquivoSemCapa($arquivo, $processos);

            if ($processo !== null) {
                $processo->adicionar($arquivo->nome(), $this->paginasComReferencia($arquivo), $arquivo->avisos);

                continue;
            }

            $restantes[] = $arquivo;

            if ($this->possuiNotas($arquivo)) {
                $avisos[] = "\"{$arquivo->caminhoRelativo}\" tem notas de empenho/liquidação mas nenhum processo correspondente; mantido como documento avulso.";
            }
        }

        return [
            'processos' => array_values(array_map(fn (ProcessoEmMontagem $processo) => $processo->documento(), $processos)),
            'restantes' => $restantes,
            'avisos' => $avisos,
        ];
    }

    /**
     * Divide o arquivo a cada capa. Páginas antes da primeira capa ficam com o primeiro processo.
     *
     * @return list<array{numero: string|null, variante: VarianteDaFolha|null, paginas: list<array{referencia: array{arquivo: string, pagina: int}, pagina: PaginaClassificada}>}>
     */
    private function segmentar(ArquivoDeEntrada $arquivo): array
    {
        /** @var list<PaginaClassificada> $capas */
        $capas = [];
        /** @var list<list<array{referencia: array{arquivo: string, pagina: int}, pagina: PaginaClassificada}>> $grupos */
        $grupos = [];
        $antesDaCapa = [];

        foreach ($this->paginasComReferencia($arquivo) as $item) {
            if ($item['pagina']->tipo->isCapa()) {
                $capas[] = $item['pagina'];
                $grupos[] = [...$antesDaCapa, $item];
                $antesDaCapa = [];

                continue;
            }

            if ($grupos === []) {
                $antesDaCapa[] = $item;

                continue;
            }

            $grupos[array_key_last($grupos)][] = $item;
        }

        return array_map(fn (PaginaClassificada $capa, array $paginas) => [
            'numero' => $capa->numeroProcesso ?? $this->numeroPeloNome($arquivo, count($capas)),
            'variante' => $this->variante($arquivo, $capa->fornecedor, $paginas),
            'paginas' => $paginas,
        ], $capas, $grupos);
    }

    /**
     * Com uma capa só no arquivo, o número no nome dele cobre falhas de OCR na capa.
     */
    private function numeroPeloNome(ArquivoDeEntrada $arquivo, int $quantidadeDeCapas): ?string
    {
        if ($quantidadeDeCapas !== 1) {
            return null;
        }

        return NomeDeArquivo::numeroDoProcesso($arquivo->nome());
    }

    /**
     * Variante da folha: pelo nome/pasta do arquivo, senão pelo conteúdo das páginas da folha.
     *
     * @param  list<array{referencia: array{arquivo: string, pagina: int}, pagina: PaginaClassificada}>  $paginas
     */
    private function variante(ArquivoDeEntrada $arquivo, ?string $fornecedor, array $paginas): ?VarianteDaFolha
    {
        $variantes = array_filter(array_map(fn (array $item) => $item['pagina']->folha, $paginas));
        $isFolha = $variantes !== [] || str_contains(Texto::normalizar((string) $fornecedor), 'FOLHA');

        if (! $isFolha) {
            return null;
        }

        if (in_array(VarianteDaFolha::Analitica, $variantes, true)) {
            return VarianteDaFolha::doNomeDoArquivo($arquivo->caminhoRelativo) ?? VarianteDaFolha::Analitica;
        }

        return VarianteDaFolha::doNomeDoArquivo($arquivo->caminhoRelativo) ?? VarianteDaFolha::Sintetica;
    }

    private function chave(?string $numero, ?VarianteDaFolha $variante, ArquivoDeEntrada $arquivo, int $indice): string
    {
        $base = $numero ?? 'sem-numero:'.$arquivo->caminhoRelativo.'#'.$indice;

        return $base.'|'.($variante->value ?? '');
    }

    /**
     * @param  array<string, ProcessoEmMontagem>  $processos
     */
    private function processoDoArquivoSemCapa(ArquivoDeEntrada $arquivo, array $processos): ?ProcessoEmMontagem
    {
        $numero = NomeDeArquivo::numeroDoProcesso($arquivo->nome());

        if ($numero !== null) {
            $variante = VarianteDaFolha::doNomeDoArquivo($arquivo->caminhoRelativo);

            return $processos[$numero.'|'.($variante->value ?? '')] ?? $this->primeiroComNumero($numero, $processos);
        }

        return $this->processoPeloEmpenho($arquivo, $processos, considerarSubEmpenho: true)
            ?? $this->processoPeloEmpenho($arquivo, $processos, considerarSubEmpenho: false);
    }

    /**
     * Processo cujas notas têm o mesmo empenho (e sub-empenho, quando considerado) de alguma página do arquivo.
     *
     * @param  array<string, ProcessoEmMontagem>  $processos
     */
    private function processoPeloEmpenho(ArquivoDeEntrada $arquivo, array $processos, bool $considerarSubEmpenho): ?ProcessoEmMontagem
    {
        foreach ($arquivo->paginas as $pagina) {
            if ($pagina->empenho === null) {
                continue;
            }

            $subEmpenho = $considerarSubEmpenho ? $pagina->subEmpenho : null;

            foreach ($processos as $processo) {
                if ($processo->possuiEmpenho($pagina->empenho, $subEmpenho)) {
                    return $processo;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, ProcessoEmMontagem>  $processos
     */
    private function primeiroComNumero(string $numero, array $processos): ?ProcessoEmMontagem
    {
        foreach ($processos as $processo) {
            if ($processo->numero === $numero) {
                return $processo;
            }
        }

        return null;
    }

    private function possuiNotas(ArquivoDeEntrada $arquivo): bool
    {
        foreach ($arquivo->paginas as $pagina) {
            if (in_array($pagina->tipo, [TipoDePagina::NotaDeLiquidacao, TipoDePagina::NotaDeEmpenho, TipoDePagina::NotaDeSubEmpenho], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{referencia: array{arquivo: string, pagina: int}, pagina: PaginaClassificada}>
     */
    private function paginasComReferencia(ArquivoDeEntrada $arquivo): array
    {
        return array_map(
            fn (array $referencia, PaginaClassificada $pagina) => ['referencia' => $referencia, 'pagina' => $pagina],
            $arquivo->referencias(),
            $arquivo->paginas,
        );
    }
}
