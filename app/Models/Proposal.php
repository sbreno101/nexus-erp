<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proposta comercial.
 *
 * Totais (subtotal, desconto, total) são calculados pelo LineItemCalculator
 * e gravados nas colunas *_cents: assim relatórios e listagens não precisam
 * somar os itens a cada consulta.
 */
#[Fillable([
    'number', 'customer_id', 'opportunity_id', 'owner_id', 'title', 'status',
    'issue_date', 'valid_until', 'subtotal_cents', 'discount_cents', 'total_cents',
    'payment_terms', 'notes',
])]
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasFactory, HasStatusHistory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ProposalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->orderBy('position');
    }

    /**
     * Contrato originado por esta proposta (no máximo um).
     *
     * @return HasOne<Contract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /**
     * @param  Builder<Proposal>  $query
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

    public function canBeConvertedToContract(): bool
    {
        return $this->status === ProposalStatus::Approved && ! $this->contract()->exists();
    }

    /**
     * Proposta enviada cuja validade já passou (candidata a "Expirada").
     */
    public function isPastValidity(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }
}
