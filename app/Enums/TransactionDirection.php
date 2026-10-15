<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Sentido de um lançamento financeiro: entrada (crédito) ou saída (débito).
 */
enum TransactionDirection: string
{
    use EnumHelpers;

    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Entrada',
            self::Out => 'Saída',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::In => 'green',
            self::Out => 'red',
        };
    }

    public function opposite(): self
    {
        return $this === self::In ? self::Out : self::In;
    }

    /**
     * Sinal aplicado ao saldo: +1 para entradas, -1 para saídas.
     */
    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }
}
