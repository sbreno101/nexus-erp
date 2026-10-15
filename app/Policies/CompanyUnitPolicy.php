<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CompanyUnitPolicy extends TenantResourcePolicy
{
    protected string $permission = 'units';

    public function create(User $user): bool
    {
        return $user->hasPermission('units.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, $model, 'units.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, $model, 'units.manage');
    }
}
