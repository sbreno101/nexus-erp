<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Status do relacionamento com o cliente.
 */
enum CustomerStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
            self::Blocked => 'Bloqueado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'gray',
            self::Blocked => 'red',
        };
    }
}
