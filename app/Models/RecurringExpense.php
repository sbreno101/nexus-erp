<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Despesa recorrente (aluguel, softwares, internet...). O Scheduler gera
 * automaticamente as contas a pagar de cada período.
 */
#[Fillable([
    'supplier_id', 'financial_category_id', 'cost_center_id', 'description', 'amount_cents',
    'frequency', 'due_day', 'start_date', 'end_date', 'next_due_date', 'is_active', 'created_by',
])]
class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'frequency' => BillingFrequency::class,
            'amount_cents' => 'integer',
            'due_day' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
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
     * @return HasMany<Payable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    /**
     * @param  Builder<RecurringExpense>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
