<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CompanyUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unidade (filial, escritório) de uma empresa.
 */
#[Fillable([
    'name', 'code', 'cnpj', 'email', 'phone', 'zip_code', 'street', 'number',
    'complement', 'district', 'city', 'state', 'is_active',
])]
class CompanyUnit extends Model
{
    /** @use HasFactory<CompanyUnitFactory> */
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
