<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Canal pelo qual o chamado foi aberto.
 */
enum TicketChannel: string
{
    use EnumHelpers;

    case Phone = 'phone';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case InPerson = 'in_person';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Telefone',
            self::Email => 'E-mail',
            self::Whatsapp => 'WhatsApp',
            self::InPerson => 'Presencial',
            self::Portal => 'Portal',
        };
    }
}
