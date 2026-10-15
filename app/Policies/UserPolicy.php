<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy\CurrentCompany;

/**
 * Gerenciamento de usuários da empresa ativa.
 *
 * Um administrador só enxerga e altera usuários que possuem vínculo com a
 * empresa ativa. Ninguém pode desativar a si mesmo nem alterar um
 * superadministrador (exceto outro superadministrador, via Gate::before).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->isMemberOfActiveCompany($target) && $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return ! $target->isSuperAdmin()
            && $this->isMemberOfActiveCompany($target)
            && $user->hasPermission('users.update');
    }

    public function toggleStatus(User $user, User $target): bool
    {
        return $user->isNot($target)
            && ! $target->isSuperAdmin()
            && $this->isMemberOfActiveCompany($target)
            && $user->hasPermission('users.deactivate');
    }

    private function isMemberOfActiveCompany(User $target): bool
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId === null) {
            return false;
        }

        // Listagens já carregam os vínculos (eager loading): reaproveitá-los
        // evita uma consulta extra por linha da tabela ao checar @can.
        if ($target->relationLoaded('memberships')) {
            return $target->memberships->contains('company_id', $companyId);
        }

        return $target->memberships()->where('company_id', $companyId)->exists();
    }
}
