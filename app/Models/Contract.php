<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\ContractStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasStatusHistory;
use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Contrato de prestação de serviços.
 *
 * Um contrato ATIVO gera cobranças (contas a receber) periodicamente, de
 * acordo com billing_frequency e billing_day. next_billing_date indica o
 * vencimento da próxima cobrança a ser gerada.
 *
 * @property int $id
 * @property int $company_id
 * @property string $number
 * @property int $customer_id
 * @property ContractStatus $status
 * @property BillingFrequency $billing_frequency
 * @property int $billing_day
 * @property int $amount_cents
 * @property int $renewal_months
 * @property bool $auto_renew
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $next_billing_date
 */
#[Fillable([
    'number', 'customer_id', 'proposal_id', 'company_unit_id', 'owner_id', 'title',
    'description', 'status', 'start_date', 'end_date', 'billing_frequency',
    'billing_day', 'amount_cents', 'financial_category_id', 'next_billing_date', 'auto_renew',
    'renewal_months', 'signed_at', 'notes',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasFactory, HasStatusHistory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'billing_frequency' => BillingFrequency::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_billing_date' => 'date',
            'signed_at' => 'date',
            'billing_day' => 'integer',
            'amount_cents' => 'integer',
            'auto_renew' => 'boolean',
            'renewal_months' => 'integer',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'finished_at' => 'datetime',
            'renewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * Unidade da empresa contratada responsável pelo contrato.
     *
     * @return BelongsTo<CompanyUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CompanyUnit::class, 'company_unit_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Categoria financeira aplicada às cobranças geradas pelo contrato.
     *
     * @return BelongsTo<FinancialCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    /**
     * @return HasMany<ContractItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<Receivable, $this>
     */
    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    /**
     * @return HasMany<ServiceOrder, $this>
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<Contract>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ContractStatus::Active);
    }

    /**
     * @param  Builder<Contract>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->whereAny(['number', 'title'], 'ilike', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer->search($term));
        });
    }

    /**
     * Valor anual estimado do contrato (receita recorrente anualizada).
     */
    public function annualValueCents(): int
    {
        return intdiv(12, $this->billing_frequency->months()) * $this->amount_cents;
    }

    /**
     * Primeiro vencimento a partir da data de início, respeitando o dia de cobrança.
     */
    public function firstBillingDate(): CarbonImmutable
    {
        $start = CarbonImmutable::parse($this->start_date);
        $candidate = $start->setDay($this->billing_day);

        return $candidate->lt($start) ? $candidate->addMonthNoOverflow() : $candidate;
    }

    /**
     * O contrato ainda está vigente na data informada?
     */
    public function isInForceOn(Carbon|CarbonImmutable $date): bool
    {
        return $this->end_date === null || $date->lte(CarbonImmutable::parse($this->end_date)->endOfDay());
    }
}
