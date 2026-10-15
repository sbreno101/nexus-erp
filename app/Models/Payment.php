<?php

namespace App\Models;

use App\Enums\EntryStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baixa (pagamento ou recebimento) de um título financeiro.
 *
 * Um pagamento NUNCA é apagado nem editado. Para corrigir um erro, ele é
 * estornado (status "reversed"), o que gera um lançamento inverso no extrato.
 */
#[Fillable([
    'receivable_id', 'payable_id', 'financial_account_id', 'amount_cents', 'paid_on',
    'method', 'notes', 'status', 'idempotency_key', 'user_id',
])]
class Payment extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
            'status' => EntryStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Receivable, $this>
     */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    /**
     * @return BelongsTo<Payable, $this>
     */
    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Lançamentos de extrato gerados por este pagamento (original e estorno).
     *
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Título ao qual o pagamento se refere (a receber ou a pagar).
     */
    public function bill(): Receivable|Payable|null
    {
        return $this->receivable ?? $this->payable;
    }

    public function isIncoming(): bool
    {
        return $this->receivable_id !== null;
    }

    public function isReversed(): bool
    {
        return $this->status === EntryStatus::Reversed;
    }
}
