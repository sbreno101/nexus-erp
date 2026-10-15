<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Origem de um lançamento no extrato (livro-razão) de uma conta financeira.
 */
enum TransactionType: string
{
    use EnumHelpers;

    case Receipt = 'receipt';
    case Payment = 'payment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Manual = 'manual';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Recebimento',
            self::Payment => 'Pagamento',
            self::TransferIn => 'Transferência recebida',
            self::TransferOut => 'Transferência enviada',
            self::Manual => 'Lançamento avulso',
            self::Reversal => 'Estorno',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Receipt => 'green',
            self::Payment => 'red',
            self::TransferIn => 'blue',
            self::TransferOut => 'blue',
            self::Manual => 'gray',
            self::Reversal => 'orange',
        };
    }
}
