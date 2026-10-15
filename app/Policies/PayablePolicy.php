<?php

namespace App\Policies;

use App\Models\Payable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PayablePolicy extends TenantResourcePolicy
{
    protected string $permission = 'payables';

    /**
     * @param  Payable  $model
     */
    public function update(User $user, Model $model): bool
    {
        return $model->status->acceptsPayment() && $model->paid_cents === 0 && parent::update($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function cancel(User $user, Payable $payable): bool
    {
        return $this->allows($user, $payable, 'payables.cancel');
    }

    public function settle(User $user, Payable $payable): bool
    {
        return $this->allows($user, $payable, 'payables.settle');
    }
}
