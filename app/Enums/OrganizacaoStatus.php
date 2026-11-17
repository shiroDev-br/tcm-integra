<?php

namespace App\Enums;

enum OrganizacaoStatus: string
{
    case Pendente = 'pendente';

    case Processando = 'processando';

    case Concluida = 'concluida';

    case Falhou = 'falhou';

    /**
     * Rótulo da situação exibido na interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Na fila',
            self::Processando => 'Processando',
            self::Concluida => 'Concluída',
            self::Falhou => 'Falhou',
        };
    }

    /**
     * Cor do flux:badge correspondente à situação.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pendente => 'zinc',
            self::Processando => 'sky',
            self::Concluida => 'green',
            self::Falhou => 'red',
        };
    }

    /**
     * Ainda vai mudar de situação sem ação do usuário.
     */
    public function isEmAndamento(): bool
    {
        return $this === self::Pendente || $this === self::Processando;
    }
}
