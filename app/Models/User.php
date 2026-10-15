<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\ThemePreference;
use App\Models\Concerns\Auditable;
use App\Support\Tenancy\CurrentCompany;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuário do sistema.
 *
 * Um usuário pode participar de várias empresas, com um perfil (Role)
 * diferente em cada uma. As permissões efetivas sempre dependem da
 * EMPRESA ATIVA. O superadministrador (is_super_admin) tem acesso total.
 */
#[Fillable(['name', 'email', 'password', 'phone', 'job_title', 'is_active', 'current_company_id', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    /**
     * Colunas que não precisam aparecer na auditoria.
     *
     * @var list<string>
     */
    protected array $auditExclude = ['last_login_at', 'last_login_ip', 'email_verified_at'];

    /**
     * Cache em memória das permissões por empresa (dura apenas a requisição).
     *
     * @var array<int, list<string>>
     */
    protected array $permissionCache = [];

    /**
     * Cache em memória das empresas acessíveis (dura apenas a requisição).
     *
     * @var Collection<int, Company>|null
     */
    protected ?Collection $accessibleCompaniesCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'theme' => ThemePreference::class,
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Company, $this, CompanyMembership, 'membership'>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(CompanyMembership::class)
            ->as('membership')
            ->withPivot(['id', 'role_id', 'company_unit_id', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Restringe a consulta aos usuários com vínculo ativo na empresa informada.
     *
     * @param  Builder<User>  $query
     */
    public function scopeMemberOf(Builder $query, int $companyId): void
    {
        $query->whereHas('memberships', fn (Builder $membership) => $membership
            ->where('company_id', $companyId)
            ->where('is_active', true));
    }

    /**
     * Lista "id => nome" dos usuários ativos de uma empresa (selects de responsável).
     *
     * @return array<int, string>
     */
    public static function optionsForCompany(?int $companyId): array
    {
        if ($companyId === null) {
            return [];
        }

        return static::query()->active()->memberOf($companyId)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Empresas que o usuário pode acessar (e selecionar como ativa).
     *
     * @return Collection<int, Company>
     */
    public function accessibleCompanies(): Collection
    {
        return $this->accessibleCompaniesCache ??= $this->queryAccessibleCompanies();
    }

    /**
     * @return Collection<int, Company>
     */
    private function queryAccessibleCompanies(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Company::query()->where('status', '!=', CompanyStatus::Inactive)->orderBy('trade_name')->get();
        }

        return $this->companies()
            ->wherePivot('is_active', true)
            ->where('companies.status', CompanyStatus::Active)
            ->orderBy('trade_name')
            ->get();
    }

    public function canAccessCompany(Company|int $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $this->accessibleCompanies()->contains('id', $companyId);
    }

    public function membershipFor(Company|int $company): ?CompanyMembership
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $this->memberships()->where('company_id', $companyId)->first();
    }

    /**
     * Lista de permissões ("customers.view", ...) do usuário em uma empresa.
     *
     * Consulta única com JOINs: company_user -> permission_role -> permissions.
     *
     * @return list<string>
     */
    public function permissionsFor(int $companyId): array
    {
        return $this->permissionCache[$companyId] ??= DB::table('company_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'company_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('company_user.user_id', $this->id)
            ->where('company_user.company_id', $companyId)
            ->where('company_user.is_active', true)
            ->pluck('permissions.name')
            ->all();
    }

    /**
     * Verifica uma permissão na empresa informada (ou na empresa ativa).
     */
    public function hasPermission(string $permission, ?int $companyId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $companyId ??= app(CurrentCompany::class)->id();

        return $companyId !== null && in_array($permission, $this->permissionsFor($companyId), true);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Limpa o cache de permissões (após alterar perfis durante a mesma requisição).
     */
    public function flushPermissionCache(): void
    {
        $this->permissionCache = [];
        $this->accessibleCompaniesCache = null;
    }

    /**
     * Iniciais para o avatar: "Maria Souza" => "MS".
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
