<?php

namespace App\Exceptions;

class SourceUnavailable extends DomainError
{
    protected int $status = 502;

    protected ?string $generic = 'fonte indisponível';
}
