<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Etapas de qualificação de um lead.
 */
enum LeadStatus: string
{
    use EnumHelpers;

    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Disqualified = 'disqualified';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::Contacted => 'Contatado',
            self::Qualified => 'Qualificado',
            self::Disqualified => 'Desqualificado',
            self::Converted => 'Convertido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::Contacted => 'indigo',
            self::Qualified => 'purple',
            self::Disqualified => 'gray',
            self::Converted => 'green',
        };
    }
}
