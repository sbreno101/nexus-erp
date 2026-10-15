<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;

/**
 * Cadastro de empresas: exclusivo do superadministrador (liberado em
 * Gate::before). Administradores da empresa apenas editam a própria empresa
 * pela tela de configurações.
 */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Company $company): bool
    {
        return $company->id === app(CurrentCompany::class)->id() && $user->hasPermission('settings.manage');
    }

    public function delete(User $user, Company $company): bool
    {
        return false;
    }
}
