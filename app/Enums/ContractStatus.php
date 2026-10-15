<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Concerns\HasStateMachine;

/**
 * Ciclo de vida de um contrato.
 *
 *   Rascunho -> Ativo <-> Suspenso
 *   Ativo/Suspenso -> Cancelado | Encerrado
 *
 * Somente contratos ATIVOS geram cobranças recorrentes.
 */
enum ContractStatus: string
{
    use EnumHelpers;
    use HasStateMachine;

    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Active => 'Ativo',
            self::Suspended => 'Suspenso',
            self::Cancelled => 'Cancelado',
            self::Finished => 'Encerrado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'green',
            self::Suspended => 'yellow',
            self::Cancelled => 'red',
            self::Finished => 'blue',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::Suspended, self::Cancelled, self::Finished],
            self::Suspended => [self::Active, self::Cancelled, self::Finished],
            self::Cancelled, self::Finished => [],
        };
    }

    public function generatesCharges(): bool
    {
        return $this === self::Active;
    }

    /**
     * Contratos encerrados ou cancelados não podem mais ser editados.
     */
    public function isEditable(): bool
    {
        return ! $this->isFinal();
    }
}
