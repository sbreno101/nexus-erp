<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RolePolicy extends TenantResourcePolicy
{
    protected string $permission = 'roles';

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    /**
     * O perfil "Administrador da empresa" sempre possui todas as permissões:
     * bloqueá-lo evitaria que a empresa perdesse o acesso administrativo.
     *
     * @param  Role  $model
     */
    public function update(User $user, Model $model): bool
    {
        return ! $model->isCompanyAdmin() && $this->allows($user, $model, 'roles.manage');
    }

    /**
     * Perfis do sistema ou em uso não podem ser excluídos.
     *
     * @param  Role  $model
     */
    public function delete(User $user, Model $model): bool
    {
        return ! $model->is_system && $this->allows($user, $model, 'roles.manage');
    }
}
