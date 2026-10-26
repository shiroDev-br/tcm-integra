<?php

namespace App\Exceptions;

class Unauthenticated extends DomainError
{
    protected int $status = 401;

    /** @var array<string, string> */
    protected array $headers = ['WWW-Authenticate' => 'Bearer'];

    public function __construct(string $message = 'Não autenticado')
    {
        parent::__construct($message);
    }
}
