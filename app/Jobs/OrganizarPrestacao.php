<?php

namespace App\Jobs;

use App\Models\Organizacao;
use App\Services\Organizador\OrganizadorDePrestacao;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Organiza uma prestação de contas em segundo plano (OCR de centenas de páginas leva minutos).
 *
 * Roda na conexão "organizador", cujo retry_after é maior que o timeout, para o job
 * não ser entregue a um segundo worker enquanto o primeiro ainda trabalha.
 */
class OrganizarPrestacao implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public Organizacao $organizacao)
    {
        $this->onConnection('organizador')->onQueue('organizador');
    }

    public function handle(OrganizadorDePrestacao $organizador): void
    {
        $this->organizacao->marcarProcessando();

        $organizador->organizar($this->organizacao);
    }

    public function failed(?Throwable $erro): void
    {
        $this->organizacao->falhar($erro?->getMessage() ?? 'Falha desconhecida ao organizar os arquivos.');
    }
}
