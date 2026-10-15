<?php

namespace App\Policies;

use App\Models\User;

class CustomerPolicy extends TenantResourcePolicy
{
    protected string $permission = 'customers';

    public function export(User $user): bool
    {
        return $user->hasPermission('customers.export');
    }
}
