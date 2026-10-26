<?php

namespace App\Exceptions;

class TermNotFound extends DomainError
{
    protected int $status = 404;
}
