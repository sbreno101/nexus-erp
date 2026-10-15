<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Tipo de conta financeira interna.
 */
enum FinancialAccountType: string
{
    use EnumHelpers;

    case Checking = 'checking';
    case Savings = 'savings';
    case Cash = 'cash';
    case Investment = 'investment';
    case DigitalWallet = 'digital_wallet';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Conta corrente',
            self::Savings => 'Poupança',
            self::Cash => 'Caixa (espécie)',
            self::Investment => 'Investimento',
            self::DigitalWallet => 'Carteira digital',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Checking => 'blue',
            self::Savings => 'green',
            self::Cash => 'yellow',
            self::Investment => 'purple',
            self::DigitalWallet => 'indigo',
        };
    }
}
