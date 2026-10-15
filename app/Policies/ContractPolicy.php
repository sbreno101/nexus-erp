<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContractPolicy extends TenantResourcePolicy
{
    protected string $permission = 'contracts';

    /**
     * @param  Contract  $model
     */
    public function update(User $user, Model $model): bool
    {
        return $model->status->isEditable() && parent::update($user, $model);
    }

    /**
     * Contratos não são excluídos: são cancelados ou encerrados (rastreabilidade).
     */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Ativar, suspender, cancelar, encerrar, renovar e gerar cobranças.
     */
    public function manage(User $user, Contract $contract): bool
    {
        return $this->allows($user, $contract, 'contracts.manage');
    }
}
