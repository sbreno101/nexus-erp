<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perfil de acesso de uma empresa (ex.: Financeiro, Comercial).
 *
 * Cada empresa recebe os perfis padrão ao ser criada e pode ajustar as
 * permissões de cada um ou criar perfis próprios.
 */
#[Fillable(['company_id', 'name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    public const string COMPANY_ADMIN = 'company-admin';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function isCompanyAdmin(): bool
    {
        return $this->slug === self::COMPANY_ADMIN;
    }
}
