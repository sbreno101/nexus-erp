<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Empresa (tenant). Raiz do isolamento de dados do sistema.
 */
#[Fillable([
    'legal_name', 'trade_name', 'cnpj', 'state_registration', 'email', 'phone',
    'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
    'status', 'logo_path',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
        ];
    }

    /**
     * @return HasOne<CompanySetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    /**
     * @return HasMany<CompanyUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(CompanyUnit::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Usuários vinculados (N:N) com os dados do vínculo (perfil, unidade, ativo).
     *
     * @return BelongsToMany<User, $this, CompanyMembership, 'membership'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
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
     * @param  Builder<Company>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CompanyStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    /**
     * Nome de exibição: nome fantasia ou, na falta dele, a razão social.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->trade_name ?: $this->legal_name);
    }

    /**
     * URL temporária e assinada do logotipo (arquivo em disco privado).
     *
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path ? route('company.logo', $this) : null);
    }

    /**
     * Retorna as configurações, criando o registro com valores padrão se necessário.
     */
    public function settingsOrDefault(): CompanySetting
    {
        if ($this->relationLoaded('settings') && $this->settings !== null) {
            return $this->settings;
        }

        $settings = $this->settings()->firstOrCreate();
        $this->setRelation('settings', $settings);

        return $settings;
    }

    public function logoContents(): ?string
    {
        return $this->logo_path && Storage::disk('local')->exists($this->logo_path)
            ? Storage::disk('local')->get($this->logo_path)
            : null;
    }
}
