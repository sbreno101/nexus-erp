<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Torna um Model "multiempresa".
 *
 * 1. Aplica o CompanyScope (filtro automático pela empresa ativa).
 * 2. Preenche company_id automaticamente ao criar o registro.
 * 3. Impede que um registro seja movido para outra empresa por engano.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            if (empty($model->company_id)) {
                $model->company_id = app(CurrentCompany::class)->id()
                    ?? throw new RuntimeException('Não é possível criar '.class_basename($model).' sem uma empresa ativa.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('company_id') && $model->getOriginal('company_id') !== null) {
                throw new RuntimeException('Não é permitido transferir registros entre empresas.');
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Consulta explícita por empresa, ignorando a empresa ativa.
     * Use somente após validar que o usuário tem acesso a essa empresa.
     *
     * @param  Builder<static>  $query
     * @param  int|list<int>  $companyIds
     */
    public function scopeForCompanies(Builder $query, int|array $companyIds): void
    {
        $query->withoutGlobalScope(CompanyScope::class)
            ->whereIn($this->qualifyColumn('company_id'), (array) $companyIds);
    }

    public function belongsToCompany(?int $companyId): bool
    {
        return $companyId !== null && (int) $this->company_id === $companyId;
    }
}
