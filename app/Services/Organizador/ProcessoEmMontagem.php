<?php

namespace App\Services\Organizador;

use App\Enums\PastaDaPrestacao;
use App\Enums\TipoDePagina;

/**
 * Processo de pagamento sendo reunido a partir de um ou mais arquivos.
 */
final class ProcessoEmMontagem
{
    /** @var list<array{referencia: array{arquivo: string, pagina: int}, tipo: TipoDePagina}> */
    private array $paginas = [];

    /** @var list<string> Nomes dos arquivos de onde vieram as páginas. */
    private array $nomesDeOrigem = [];

    /** @var list<string> Avisos herdados dos arquivos de origem. */
    private array $avisosDaOrigem = [];

    /** @var array<string, true> Chaves "empenho#sub" e "empenho" vistas nas notas. */
    private array $empenhos = [];

    private bool $isExtra = false;

    private ?string $fornecedor = null;

    public function __construct(
        public readonly ?string $numero,
        public readonly ?VarianteDaFolha $variante,
    ) {}

    /**
     * Acrescenta páginas de um arquivo, mantendo a ordem em que chegaram.
     *
     * @param  list<array{referencia: array{arquivo: string, pagina: int}, pagina: PaginaClassificada}>  $paginas
     * @param  list<string>  $avisosDaOrigem
     */
    public function adicionar(string $nomeDeOrigem, array $paginas, array $avisosDaOrigem = []): void
    {
        $this->nomesDeOrigem[] = $nomeDeOrigem;
        $this->avisosDaOrigem = array_values(array_unique([...$this->avisosDaOrigem, ...$avisosDaOrigem]));

        foreach ($paginas as ['referencia' => $referencia, 'pagina' => $pagina]) {
            $this->registrar($pagina);
            $this->paginas[] = ['referencia' => $referencia, 'tipo' => $pagina->tipo];
        }
    }

    /**
     * Whether a nota with this empenho (and sub-empenho, when known) belongs here.
     */
    public function possuiEmpenho(string $empenho, ?string $subEmpenho): bool
    {
        return isset($this->empenhos[$this->chaveDoEmpenho($empenho, $subEmpenho)]);
    }

    public function isFolha(): bool
    {
        return $this->variante !== null;
    }

    public function documento(): DocumentoDeSaida
    {
        return new DocumentoDeSaida(
            pasta: $this->pasta(),
            nome: NomeDeArquivo::seguro($this->nome()),
            paginas: array_column($this->paginasOrdenadas(), 'referencia'),
            avisos: $this->avisos(),
        );
    }

    private function registrar(PaginaClassificada $pagina): void
    {
        if ($pagina->tipo === TipoDePagina::OrdemDePagamentoExtra) {
            $this->isExtra = true;
        }

        if ($pagina->tipo->isCapa() && $this->fornecedor === null) {
            $this->fornecedor = $pagina->fornecedor;
        }

        if ($pagina->empenho === null) {
            return;
        }

        $this->empenhos[$this->chaveDoEmpenho($pagina->empenho, $pagina->subEmpenho)] = true;
        $this->empenhos[$this->chaveDoEmpenho($pagina->empenho, null)] = true;
    }

    private function chaveDoEmpenho(string $empenho, ?string $subEmpenho): string
    {
        return $subEmpenho === null ? $empenho : $empenho.'#'.$subEmpenho;
    }

    /**
     * Ordem da planilha do TCM; páginas do mesmo tipo mantêm a ordem original (usort é estável).
     *
     * @return list<array{referencia: array{arquivo: string, pagina: int}, tipo: TipoDePagina}>
     */
    private function paginasOrdenadas(): array
    {
        $paginas = $this->paginas;

        usort($paginas, fn (array $a, array $b) => $a['tipo']->ordem() <=> $b['tipo']->ordem());

        return $paginas;
    }

    private function pasta(): PastaDaPrestacao
    {
        if ($this->isExtra) {
            return PastaDaPrestacao::Extra;
        }

        return $this->variante?->pasta() ?? PastaDaPrestacao::ProcessoPagamento;
    }

    /**
     * Reaproveita o nome dado na origem quando já segue o padrão; senão monta "PP <número> <FORNECEDOR>".
     */
    private function nome(): string
    {
        $nomeDeOrigem = $this->nomeDeOrigemNoPadrao();

        if ($nomeDeOrigem !== null) {
            return $this->comVariante($nomeDeOrigem);
        }

        $partes = [
            $this->isExtra ? 'PPE' : 'PP',
            $this->numero ?? 'SEM NUMERO',
            $this->fornecedor === null ? null : NomeDeArquivo::fornecedorCurto($this->fornecedor),
        ];

        return $this->comVariante(implode(' ', array_filter($partes)));
    }

    private function nomeDeOrigemNoPadrao(): ?string
    {
        if ($this->numero === null) {
            return null;
        }

        foreach ($this->nomesDeOrigem as $nome) {
            if (NomeDeArquivo::isNomeDeProcesso($nome, $this->numero)) {
                return NomeDeArquivo::semParte($nome);
            }
        }

        return null;
    }

    private function comVariante(string $nome): string
    {
        if ($this->variante === null || VarianteDaFolha::doNomeDoArquivo($nome) !== null) {
            return $nome;
        }

        return $nome.' '.$this->variante->value;
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        $avisos = $this->avisosDaOrigem;
        $tipos = array_unique(array_map(fn (array $pagina) => $pagina['tipo'], $this->paginas), SORT_REGULAR);

        if ($this->numero === null) {
            $avisos[] = 'Número do processo não encontrado na capa.';
        }

        foreach ($this->pecasObrigatorias() as $peca) {
            if (! in_array($peca, $tipos, true)) {
                $avisos[] = 'Sem '.$peca->label().'.';
            }
        }

        $capas = count(array_filter($this->paginas, fn (array $pagina) => $pagina['tipo']->isCapa()));

        if ($capas > 1) {
            $avisos[] = "Unificado de {$capas} capas (".implode(', ', array_unique($this->nomesDeOrigem)).'); confira páginas repetidas.';
        }

        return $avisos;
    }

    /**
     * @return list<TipoDePagina>
     */
    private function pecasObrigatorias(): array
    {
        if ($this->isExtra) {
            return [TipoDePagina::GuiaDeDespesa, TipoDePagina::Comprovante];
        }

        $notas = [TipoDePagina::NotaDeLiquidacao, TipoDePagina::NotaDeEmpenho, TipoDePagina::NotaDeSubEmpenho];

        // A folha sintética é só o resumo; os comprovantes ficam na analítica.
        if ($this->variante === VarianteDaFolha::Sintetica) {
            return $notas;
        }

        return [...$notas, TipoDePagina::Comprovante];
    }
}
