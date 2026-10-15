<?php

namespace App\Policies;

use App\Models\User;

class SupplierPolicy extends TenantResourcePolicy
{
    protected string $permission = 'suppliers';

    public function export(User $user): bool
    {
        return $user->hasPermission('suppliers.export');
    }
}
