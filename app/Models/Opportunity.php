<?php

namespace App\Models;

use App\Enums\OpportunityStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRelationshipHistory;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Oportunidade de venda em uma etapa do funil.
 */
#[Fillable([
    'title', 'customer_id', 'lead_id', 'pipeline_stage_id', 'owner_id',
    'estimated_value_cents', 'probability', 'expected_close_date', 'status',
    'position', 'lost_reason', 'closed_at', 'description',
])]
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use Auditable, BelongsToCompany, HasFactory, HasRelationshipHistory, HasStatusHistory, SoftDeletes;

    protected array $auditExclude = ['position'];

    protected function casts(): array
    {
        return [
            'status' => OpportunityStatus::class,
            'estimated_value_cents' => 'integer',
            'probability' => 'integer',
            'position' => 'integer',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @param  Builder<Opportunity>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Open);
    }

    /**
     * Valor ponderado pela probabilidade (centavos), usado na previsão de vendas.
     */
    public function weightedValueCents(): int
    {
        return intdiv($this->estimated_value_cents * $this->probability, 100);
    }

    /**
     * Nome de quem está sendo negociado (cliente ou lead).
     */
    public function counterpartName(): string
    {
        return $this->customer?->display_name ?? $this->lead?->company_name ?? $this->lead?->name ?? '—';
    }
}
