<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Status cadastral genérico (fornecedores, unidades e afins).
 */
enum RecordStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'gray',
        };
    }
}
