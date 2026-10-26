<?php

namespace App\Exceptions;

class Forbidden extends DomainError
{
    protected int $status = 403;

    public function __construct(string $message = 'Acesso negado')
    {
        parent::__construct($message);
    }
}
