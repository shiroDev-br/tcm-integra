<?php

namespace App\Services\Organizador;

/**
 * PDF recebido, já com OCR, e a classificação de cada página.
 */
final readonly class ArquivoDeEntrada
{
    /**
     * @param  string  $caminho  Caminho absoluto do PDF usado na montagem.
     * @param  string  $caminhoRelativo  Caminho original dentro do envio (pastas + nome).
     * @param  list<PaginaClassificada>  $paginas
     * @param  string  $hash  Hash do conteúdo original, para descartar arquivos repetidos.
     * @param  list<string>  $avisos  Problemas encontrados ao preparar o arquivo (OCR, páginas sem texto).
     */
    public function __construct(
        public string $caminho,
        public string $caminhoRelativo,
        public array $paginas,
        public string $textoDaPrimeiraPagina,
        public string $hash,
        public array $avisos = [],
    ) {}

    /**
     * Nome do arquivo sem a extensão.
     */
    public function nome(): string
    {
        return pathinfo($this->caminhoRelativo, PATHINFO_FILENAME);
    }

    public function possuiCapa(): bool
    {
        foreach ($this->paginas as $pagina) {
            if ($pagina->tipo->isCapa()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Referência de cada página (arquivo + número, a partir de 1) para a montagem.
     *
     * @return list<array{arquivo: string, pagina: int}>
     */
    public function referencias(): array
    {
        return array_map(
            fn (int $indice) => ['arquivo' => $this->caminho, 'pagina' => $indice + 1],
            array_keys($this->paginas),
        );
    }
}
