<?php

namespace App\Exceptions;

class UserNotFound extends DomainError
{
    protected int $status = 404;

    public function __construct(string $message = 'usuário não encontrado')
    {
        parent::__construct($message);
    }
}
