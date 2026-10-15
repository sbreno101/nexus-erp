<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRelationshipHistory;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lead: contato comercial ainda não convertido em cliente.
 */
#[Fillable([
    'name', 'company_name', 'email', 'phone', 'document', 'lead_source_id', 'status',
    'estimated_value_cents', 'owner_id', 'next_contact_at', 'notes',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use Auditable, BelongsToCompany, HasFactory, HasRelationshipHistory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'estimated_value_cents' => 'integer',
            'converted_at' => 'datetime',
            'next_contact_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<LeadSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Cliente gerado pela conversão deste lead.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->whereAny(['name', 'company_name', 'email', 'phone'], 'ilike', "%{$term}%");
        }
    }

    public function isConverted(): bool
    {
        return $this->status === LeadStatus::Converted;
    }
}
