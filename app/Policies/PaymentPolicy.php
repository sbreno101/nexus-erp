<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy extends TenantResourcePolicy
{
    protected string $permission = 'payments';

    public function reverse(User $user, Payment $payment): bool
    {
        return ! $payment->isReversed() && $this->allows($user, $payment, 'payments.reverse');
    }
}
