<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecurringExpensePolicy extends TenantResourcePolicy
{
    protected string $permission = 'payables';

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.configure');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, $model, 'finance.configure');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, $model, 'finance.configure');
    }
}
