<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Periodicidade de cobrança de contratos e despesas recorrentes.
 */
enum BillingFrequency: string
{
    use EnumHelpers;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Quarterly => 'Trimestral',
            self::Semiannual => 'Semestral',
            self::Annual => 'Anual',
        };
    }

    /**
     * Quantidade de meses entre duas cobranças.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Semiannual => 6,
            self::Annual => 12,
        };
    }
}
