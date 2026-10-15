<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Tipo de interação registrada no histórico de relacionamento.
 */
enum InteractionType: string
{
    use EnumHelpers;

    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Whatsapp = 'whatsapp';
    case Visit = 'visit';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Anotação',
            self::Call => 'Ligação',
            self::Email => 'E-mail',
            self::Meeting => 'Reunião',
            self::Whatsapp => 'WhatsApp',
            self::Visit => 'Visita',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Note => 'gray',
            self::Call => 'blue',
            self::Email => 'indigo',
            self::Meeting => 'purple',
            self::Whatsapp => 'green',
            self::Visit => 'orange',
        };
    }
}
