<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Preferência de tema da interface.
 */
enum ThemePreference: string
{
    use EnumHelpers;

    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Seguir o sistema',
            self::Light => 'Claro',
            self::Dark => 'Escuro',
        };
    }
}
