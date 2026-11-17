<?php

namespace App\Services\Organizador;

use App\Enums\TipoDePagina;

/**
 * O que foi reconhecido no texto de uma página.
 */
final readonly class PaginaClassificada
{
    public function __construct(
        public TipoDePagina $tipo,
        public ?string $numeroProcesso = null,
        public ?string $empenho = null,
        public ?string $subEmpenho = null,
        public ?string $fornecedor = null,
        public ?VarianteDaFolha $folha = null,
    ) {}
}
