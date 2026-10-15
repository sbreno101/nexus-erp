<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Concerns\HasStateMachine;

/**
 * Ciclo de vida de uma proposta comercial.
 *
 *   Rascunho -> Enviada -> Aprovada | Recusada | Expirada
 *   Recusada/Expirada -> Rascunho (revisão)
 *   Cancelada é possível a partir de qualquer status não final.
 */
enum ProposalStatus: string
{
    use EnumHelpers;
    use HasStateMachine;

    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Sent => 'Enviada',
            self::Approved => 'Aprovada',
            self::Rejected => 'Recusada',
            self::Expired => 'Expirada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'blue',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Expired => 'yellow',
            self::Cancelled => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Approved, self::Rejected, self::Expired, self::Draft, self::Cancelled],
            self::Approved => [self::Cancelled],
            self::Rejected, self::Expired => [self::Draft, self::Cancelled],
            self::Cancelled => [],
        };
    }

    /**
     * Somente rascunhos podem ter itens e valores editados.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Propostas consideradas "em aberto" nos indicadores comerciais.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Draft, self::Sent];
    }
}
