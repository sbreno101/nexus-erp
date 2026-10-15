<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Meio de pagamento informado na baixa MANUAL de um título.
 * Não há processamento real de pagamentos: apenas o registro da operação.
 */
enum PaymentMethod: string
{
    use EnumHelpers;

    case Pix = 'pix';
    case BankSlip = 'bank_slip';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::BankSlip => 'Boleto',
            self::BankTransfer => 'Transferência/TED',
            self::Cash => 'Dinheiro',
            self::CreditCard => 'Cartão de crédito',
            self::DebitCard => 'Cartão de débito',
            self::Other => 'Outro',
        };
    }
}
