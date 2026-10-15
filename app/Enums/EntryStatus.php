<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Status de pagamentos e transferências: nunca são apagados, apenas estornados.
 */
enum EntryStatus: string
{
    use EnumHelpers;

    case Confirmed = 'confirmed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado',
            self::Reversed => 'Estornado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'green',
            self::Reversed => 'red',
        };
    }
}
