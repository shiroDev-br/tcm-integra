<?php

namespace App\Enums;

/**
 * How the Diário Oficial text ties a process to the searched term.
 */
enum EvidenciaDoVinculo: string
{
    /** The term is inside the process' own record (after the number, before the next one). */
    case Forte = 'forte';

    /** The term only appears right before the number ("Prefeitura de X ... Processo nº"). */
    case Fraca = 'fraca';

    /**
     * Whether the link stands given what the TCM portal says about the process: a strong
     * link only falls when the portal contradicts it; a weak one needs the portal to confirm.
     */
    public function aceita(VereditoDoPortal $veredito): bool
    {
        return match ($this) {
            self::Forte => $veredito !== VereditoDoPortal::Contradiz,
            self::Fraca => $veredito === VereditoDoPortal::Confirma,
        };
    }
}
