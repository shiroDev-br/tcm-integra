<?php

namespace App\Services\Organizador;

use App\Enums\PastaDaPrestacao;

/**
 * PDF a ser gerado no zip organizado: pasta, nome e páginas de origem na ordem final.
 */
final readonly class DocumentoDeSaida
{
    /**
     * @param  string  $nome  Nome sem a extensão.
     * @param  list<array{arquivo: string, pagina: int}>  $paginas
     * @param  list<string>  $avisos
     * @param  string|null  $arquivoInteiro  PDF de origem usado por inteiro, copiado sem regravar (preserva assinatura digital).
     */
    public function __construct(
        public PastaDaPrestacao $pasta,
        public string $nome,
        public array $paginas,
        public array $avisos = [],
        public ?string $arquivoInteiro = null,
    ) {}
}
