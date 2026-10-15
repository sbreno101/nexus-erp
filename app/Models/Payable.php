<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\IsBill;
use Database\Factories\PayableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conta a pagar (despesa). Comprovantes são anexados via HasAttachments.
 */
#[Fillable([
    'supplier_id', 'recurring_expense_id', 'financial_category_id', 'cost_center_id', 'description',
    'document_number', 'amount_cents', 'issue_date', 'due_date', 'competence', 'notes', 'created_by',
])]
class Payable extends Model
{
    /** @use HasFactory<PayableFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasFactory, IsBill;

    public const string PAYMENT_FOREIGN_KEY = 'payable_id';

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<RecurringExpense, $this>
     */
    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class)->withTrashed();
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
     * @param  Builder<Payable>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->whereAny(['description', 'document_number'], 'ilike', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->search($term));
        });
    }
}
