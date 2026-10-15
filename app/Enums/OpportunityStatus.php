<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Resultado de uma oportunidade comercial.
 */
enum OpportunityStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Em aberto',
            self::Won => 'Ganha',
            self::Lost => 'Perdida',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Won => 'green',
            self::Lost => 'red',
        };
    }
}
