<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Concerns\HasStateMachine;

/**
 * Ciclo de vida de uma ordem de serviço.
 *
 *   Aberta -> Em execução <-> Aguardando
 *   Em execução -> Concluída (pode ser reaberta para Em execução)
 *   Cancelada é final.
 */
enum ServiceOrderStatus: string
{
    use EnumHelpers;
    use HasStateMachine;

    case Open = 'open';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::InProgress => 'Em execução',
            self::OnHold => 'Aguardando',
            self::Completed => 'Concluída',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'indigo',
            self::OnHold => 'yellow',
            self::Completed => 'green',
            self::Cancelled => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::OnHold, self::Cancelled],
            self::InProgress => [self::OnHold, self::Completed, self::Cancelled],
            self::OnHold => [self::InProgress, self::Cancelled],
            self::Completed => [self::InProgress],
            self::Cancelled => [],
        };
    }

    /**
     * Status considerados "pendentes" no dashboard.
     *
     * @return list<self>
     */
    public static function pending(): array
    {
        return [self::Open, self::InProgress, self::OnHold];
    }

    /**
     * OS concluída ou cancelada não recebe novas atividades (reabra antes).
     */
    public function acceptsActivities(): bool
    {
        return in_array($this, self::pending(), true);
    }
}
