<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conta financeira interna (banco, caixa, carteira digital).
 *
 * current_balance_cents NÃO é preenchido manualmente: só o LedgerService o
 * altera, sempre junto com a gravação de um FinancialTransaction.
 */
#[Fillable([
    'name', 'type', 'bank_name', 'bank_code', 'agency', 'account_number',
    'opening_balance_cents', 'opening_date', 'is_active',
])]
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'opening_balance_cents' => 'integer',
            'current_balance_cents' => 'integer',
            'opening_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @param  Builder<FinancialAccount>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Saldo recalculado a partir do extrato: saldo inicial + entradas - saídas.
     * Deve ser sempre igual a current_balance_cents.
     */
    public function ledgerBalanceCents(): int
    {
        $movements = (int) $this->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE -amount_cents END), 0) AS total")
            ->value('total');

        return $this->opening_balance_cents + $movements;
    }

    public function bankDescription(): string
    {
        return collect([$this->bank_name, $this->agency ? 'Ag. '.$this->agency : null, $this->account_number ? 'C/C '.$this->account_number : null])
            ->filter()
            ->implode(' · ');
    }
}
