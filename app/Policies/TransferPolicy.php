<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy extends TenantResourcePolicy
{
    protected string $permission = 'transfers';

    public function reverse(User $user, Transfer $transfer): bool
    {
        return ! $transfer->isReversed() && $this->allows($user, $transfer, 'payments.reverse');
    }
}
