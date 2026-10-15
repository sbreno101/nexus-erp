<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Status de um título financeiro (conta a receber ou a pagar).
 *
 * "Vencido" NÃO é um status armazenado: é derivado de (status em aberto ou
 * parcial + vencimento no passado). Assim o dado nunca fica desatualizado
 * esperando uma rotina noturna atualizar o banco.
 */
enum BillStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Em aberto',
            self::Partial => 'Pago parcialmente',
            self::Paid => 'Quitado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Partial => 'yellow',
            self::Paid => 'green',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Status que ainda aceitam pagamento.
     *
     * @return list<self>
     */
    public static function outstanding(): array
    {
        return [self::Open, self::Partial];
    }

    public function acceptsPayment(): bool
    {
        return in_array($this, self::outstanding(), true);
    }
}
