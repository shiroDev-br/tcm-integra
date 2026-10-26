<?php

namespace App\Services;

use App\Enums\EvidenciaDoVinculo;

/**
 * Where and how strongly the Diário Oficial ties a process to the searched term.
 */
final readonly class Evidencia
{
    public function __construct(
        public EvidenciaDoVinculo $forca,
        /** The record text that mentions the term (checked against the portal's manager name). */
        public string $trecho,
    ) {}
}
