<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Status cadastral interno de uma empresa.
 */
enum CompanyStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativa',
            self::Suspended => 'Suspensa',
            self::Inactive => 'Inativa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Suspended => 'yellow',
            self::Inactive => 'gray',
        };
    }
}
