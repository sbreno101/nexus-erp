<?php

namespace App\Policies;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProposalPolicy extends TenantResourcePolicy
{
    protected string $permission = 'proposals';

    /**
     * Itens e valores só podem ser editados enquanto a proposta é rascunho.
     *
     * @param  Proposal  $model
     */
    public function update(User $user, Model $model): bool
    {
        return $model->status->isEditable() && parent::update($user, $model);
    }

    /**
     * Excluir somente rascunhos (propostas enviadas fazem parte do histórico comercial).
     *
     * @param  Proposal  $model
     */
    public function delete(User $user, Model $model): bool
    {
        return $model->status->isEditable() && parent::delete($user, $model);
    }

    /**
     * Enviar, aprovar, recusar, cancelar e reabrir.
     */
    public function changeStatus(User $user, Proposal $proposal): bool
    {
        return $this->allows($user, $proposal, 'proposals.approve');
    }

    public function convert(User $user, Proposal $proposal): bool
    {
        return $this->allows($user, $proposal, 'contracts.create');
    }
}
