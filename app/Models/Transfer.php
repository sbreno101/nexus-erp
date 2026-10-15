<?php

namespace App\Models;

use App\Enums\EntryStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transferência entre contas financeiras da mesma empresa.
 * Gera dois lançamentos: saída na origem e entrada no destino.
 */
#[Fillable([
    'from_account_id', 'to_account_id', 'amount_cents', 'transferred_on',
    'description', 'status', 'idempotency_key', 'user_id',
])]
class Transfer extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'transferred_on' => 'date',
            'status' => EntryStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'from_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'to_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function isReversed(): bool
    {
        return $this->status === EntryStatus::Reversed;
    }
}
