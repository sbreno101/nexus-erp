<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\PersonType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasContactDetails;
use App\Models\Concerns\HasRelationshipHistory;
use App\Models\Concerns\IsBusinessParty;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cliente (pessoa física ou jurídica).
 *
 * É o centro dos fluxos de negócio: oportunidades, propostas, contratos,
 * cobranças, ordens de serviço e chamados apontam para um cliente.
 *
 * As anotações @property ajudam o editor (autocompletar) e a análise
 * estática (Larastan) a conhecer as colunas e seus tipos.
 *
 * @property int $id
 * @property int $company_id
 * @property PersonType $type
 * @property string $name
 * @property string|null $trade_name
 * @property string $document
 * @property string|null $state_registration
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $website
 * @property CustomerStatus $status
 * @property string|null $notes
 * @property int|null $owner_id
 * @property-read string $display_name
 * @property-read string $formatted_document
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type', 'name', 'trade_name', 'document', 'state_registration', 'email',
    'phone', 'mobile', 'website', 'status', 'notes', 'owner_id',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, BelongsToCompany, HasAttachments, HasContactDetails, HasFactory, HasRelationshipHistory, IsBusinessParty, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => PersonType::class,
            'status' => CustomerStatus::class,
        ];
    }

    /**
     * Responsável comercial pela conta.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Lead que originou o cliente (quando veio de uma conversão do CRM).
     *
     * @return HasOne<Lead, $this>
     */
    public function originLead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
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
     * @param  Builder<Customer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CustomerStatus::Active);
    }
}
