<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope de isolamento multiempresa.
 *
 * Adiciona "WHERE tabela.company_id = <empresa ativa>" a TODA consulta dos
 * models que usam o trait BelongsToCompany — inclusive no Route Model Binding.
 * Assim, se um usuário da empresa A tentar abrir /clientes/15 e o cliente 15
 * pertencer à empresa B, o Laravel responde 404 porque a consulta não o encontra.
 *
 * Para consultas intencionalmente multiempresa (ex.: dashboard consolidado),
 * use explicitamente ->withoutGlobalScope(CompanyScope::class).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId !== null) {
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        }
    }
}
