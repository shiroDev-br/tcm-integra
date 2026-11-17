<?php

namespace App\Services\Organizador;

use App\Enums\PastaDaPrestacao;

/**
 * PDF final gravado na pasta de saída.
 */
final readonly class ArquivoGerado
{
    /**
     * @param  string  $nome  Nome com a extensão.
     * @param  list<string>  $avisos
     */
    public function __construct(
        public PastaDaPrestacao $pasta,
        public string $nome,
        public int $paginas,
        public int $bytes,
        public array $avisos,
    ) {}

    /**
     * Linha de organizacao_arquivos.
     *
     * @return array{pasta: string, nome: string, paginas: int, tamanho_bytes: int, avisos: list<string>}
     */
    public function paraRegistro(): array
    {
        return [
            'pasta' => $this->pasta->value,
            'nome' => $this->nome,
            'paginas' => $this->paginas,
            'tamanho_bytes' => $this->bytes,
            'avisos' => $this->avisos,
        ];
    }
}
