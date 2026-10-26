<?php

namespace App\Exceptions;

class UserAlreadyExists extends DomainError
{
    protected int $status = 409;

    public function __construct(string $message = 'usuário já existe')
    {
        parent::__construct($message);
    }
}
