<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Lançamento no extrato de uma conta financeira (livro-razão).
 *
 * É IMUTÁVEL: depois de gravado não pode ser alterado nem excluído (os
 * eventos updating/deleting lançam exceção). Correções são feitas com um
 * novo lançamento de estorno (reversal_of_id aponta para o original).
 */
#[Fillable([
    'financial_account_id', 'type', 'direction', 'amount_cents', 'balance_after_cents',
    'description', 'occurred_on', 'financial_category_id', 'payment_id', 'transfer_id',
    'reversal_of_id', 'user_id',
])]
class FinancialTransaction extends Model
{
    use BelongsToCompany;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Lançamentos financeiros são imutáveis. Utilize um estorno.'));
        static::deleting(fn () => throw new LogicException('Lançamentos financeiros não podem ser excluídos. Utilize um estorno.'));
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<FinancialCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Lançamento original que este estorno anula.
     *
     * @return BelongsTo<FinancialTransaction, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * Estorno deste lançamento, se houver.
     *
     * @return HasOne<FinancialTransaction, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Valor com sinal: positivo para entradas, negativo para saídas.
     */
    public function signedAmountCents(): int
    {
        return $this->direction->sign() * $this->amount_cents;
    }
}
