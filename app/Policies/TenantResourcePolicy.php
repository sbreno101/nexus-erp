<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Base das Policies de recursos multiempresa.
 *
 * Cada Policy filha define apenas o prefixo da permissão (ex.: "customers").
 * As habilidades padrão verificam DUAS coisas:
 *   1. o perfil do usuário possui a permissão "prefixo.ação";
 *   2. o registro pertence à empresa ativa (defesa em profundidade: o
 *      CompanyScope já filtra as consultas, mas a Policy confere de novo).
 *
 * O superadministrador é liberado antes, em Gate::before (AppServiceProvider).
 */
abstract class TenantResourcePolicy
{
    /**
     * Prefixo das permissões deste recurso (ex.: "customers").
     */
    protected string $permission;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission("{$this->permission}.view");
    }

    public function view(User $user, Model $model): bool
    {
        return $this->belongsToActiveCompany($model) && $user->hasPermission("{$this->permission}.view");
    }

    public function create(User $user): bool
    {
        return $user->hasPermission("{$this->permission}.create");
    }

    public function update(User $user, Model $model): bool
    {
        return $this->belongsToActiveCompany($model) && $user->hasPermission("{$this->permission}.update");
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->belongsToActiveCompany($model) && $user->hasPermission("{$this->permission}.delete");
    }

    /**
     * Enviar/remover anexos e registrar histórico: exige permissão de edição
     * do recurso, independentemente do status do registro.
     */
    public function attach(User $user, Model $model): bool
    {
        return $this->allows($user, $model, "{$this->permission}.update");
    }

    protected function belongsToActiveCompany(Model $model): bool
    {
        $companyId = app(CurrentCompany::class)->id();

        return $companyId !== null && (int) $model->getAttribute('company_id') === $companyId;
    }

    /**
     * Verifica pertencimento à empresa + uma permissão específica.
     */
    protected function allows(User $user, Model $model, string $permission): bool
    {
        return $this->belongsToActiveCompany($model) && $user->hasPermission($permission);
    }
}
