<?php

namespace App\Exceptions;

class OperationNotAllowed extends DomainError
{
    protected int $status = 403;

    public function __construct(string $message = 'operação não permitida')
    {
        parent::__construct($message);
    }
}
