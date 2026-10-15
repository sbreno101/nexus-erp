<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FinancialAccountPolicy extends TenantResourcePolicy
{
    protected string $permission = 'accounts';

    public function create(User $user): bool
    {
        return $user->hasPermission('accounts.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, $model, 'accounts.manage');
    }

    /**
     * Contas com movimentação não são excluídas; podem ser inativadas.
     */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
