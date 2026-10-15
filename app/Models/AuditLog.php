<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro da trilha de auditoria. Somente inserção: nunca é editado.
 *
 * Não usa o trait BelongsToCompany porque alguns eventos não pertencem a
 * uma empresa (ex.: cadastro de empresas pelo superadministrador). As
 * consultas de tela filtram explicitamente com scopeForCompany().
 */
#[Fillable([
    'company_id', 'user_id', 'event', 'auditable_type', 'auditable_id',
    'description', 'old_values', 'new_values', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeForCompany(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }

    /**
     * Rótulo amigável do evento.
     */
    public function eventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Criação',
            'updated' => 'Alteração',
            'deleted' => 'Exclusão',
            'restored' => 'Restauração',
            default => str($this->event)->replace(['.', '_'], ' ')->ucfirst()->toString(),
        };
    }

    /**
     * Nome amigável do tipo de recurso auditado.
     */
    public function resourceLabel(): string
    {
        return config("morph-labels.{$this->auditable_type}", (string) $this->auditable_type);
    }
}
