<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\IsBill;
use Database\Factories\ReceivableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conta a receber (título de cobrança de um cliente).
 *
 * Pode ser avulsa ou gerada automaticamente a partir de um contrato
 * (contract_id + competence identificam a cobrança recorrente).
 * Títulos financeiros não são excluídos: são cancelados.
 */
#[Fillable([
    'customer_id', 'contract_id', 'financial_category_id', 'cost_center_id', 'description',
    'document_number', 'amount_cents', 'issue_date', 'due_date', 'competence', 'notes', 'created_by',
])]
class Receivable extends Model
{
    /** @use HasFactory<ReceivableFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasFactory, IsBill;

    public const string PAYMENT_FOREIGN_KEY = 'receivable_id';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    /**
     * @return BelongsTo<FinancialCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @param  Builder<Receivable>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->whereAny(['description', 'document_number'], 'ilike', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer->search($term));
        });
    }
}
