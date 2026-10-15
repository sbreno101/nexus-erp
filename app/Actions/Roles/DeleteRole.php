<?php

namespace App\Actions\Roles;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Exclui um perfil de acesso personalizado da empresa ativa.
 *
 * Regras:
 *  - perfis do sistema (criados automaticamente) não podem ser excluídos;
 *  - perfis com usuários vinculados também não: o banco impediria
 *    (company_user.role_id é restrictOnDelete) e o usuário ficaria sem perfil.
 */
class DeleteRole
{
    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw new BusinessRuleException('Perfis padrão do sistema não podem ser excluídos.');
        }

        $membershipsCount = $role->memberships()->count();

        if ($membershipsCount > 0) {
            throw new BusinessRuleException(trans_choice(
                'Este perfil está atribuído a :count usuário. Altere o perfil dele antes de excluir.|Este perfil está atribuído a :count usuários. Altere o perfil deles antes de excluir.',
                $membershipsCount,
            ));
        }

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });
    }
}
