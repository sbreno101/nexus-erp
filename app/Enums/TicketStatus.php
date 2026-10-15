<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Concerns\HasStateMachine;

/**
 * Ciclo de vida de um chamado de atendimento.
 */
enum TicketStatus: string
{
    use EnumHelpers;
    use HasStateMachine;

    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::InProgress => 'Em atendimento',
            self::WaitingCustomer => 'Aguardando cliente',
            self::Resolved => 'Resolvido',
            self::Closed => 'Encerrado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'indigo',
            self::WaitingCustomer => 'yellow',
            self::Resolved => 'green',
            self::Closed => 'gray',
            self::Cancelled => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::WaitingCustomer, self::Resolved, self::Cancelled],
            self::InProgress => [self::WaitingCustomer, self::Resolved, self::Cancelled],
            self::WaitingCustomer => [self::InProgress, self::Resolved, self::Cancelled],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed, self::Cancelled => [],
        };
    }

    /**
     * Status considerados "em aberto" nos indicadores.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Open, self::InProgress, self::WaitingCustomer];
    }
}
