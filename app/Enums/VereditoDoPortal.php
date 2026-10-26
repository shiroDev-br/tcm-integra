<?php

namespace App\Enums;

/**
 * What the TCM process portal says about a process belonging to a term.
 */
enum VereditoDoPortal: string
{
    /** Its municipality, author or description names the term. */
    case Confirma = 'confirma';

    /** It is from another municipality. */
    case Contradiz = 'contradiz';

    /** No municipality reported and nothing naming the term (or the lookup failed). */
    case Neutro = 'neutro';
}
