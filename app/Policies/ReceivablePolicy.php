<?php

namespace App\Policies;

use App\Models\Receivable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReceivablePolicy extends TenantResourcePolicy
{
    protected string $permission = 'receivables';

    /**
     * Só títulos sem nenhum pagamento podem ter dados editados.
     *
     * @param  Receivable  $model
     */
    public function update(User $user, Model $model): bool
    {
        return $model->status->acceptsPayment() && $model->paid_cents === 0 && parent::update($user, $model);
    }

    /**
     * Títulos financeiros nunca são excluídos: são cancelados.
     */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function cancel(User $user, Receivable $receivable): bool
    {
        return $this->allows($user, $receivable, 'receivables.cancel');
    }

    public function settle(User $user, Receivable $receivable): bool
    {
        return $this->allows($user, $receivable, 'receivables.settle');
    }
}
