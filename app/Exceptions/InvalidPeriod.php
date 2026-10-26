<?php

namespace App\Exceptions;

class InvalidPeriod extends DomainError
{
    protected int $status = 422;
}
