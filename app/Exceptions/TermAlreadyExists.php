<?php

namespace App\Exceptions;

class TermAlreadyExists extends DomainError
{
    protected int $status = 409;
}
