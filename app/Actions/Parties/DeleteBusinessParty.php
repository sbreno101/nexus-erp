<?php

namespace App\Actions\Parties;

use App\Enums\BillStatus;
use App\Enums\ContractStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Supplier;

/**
 * Exclui (soft delete) um cliente ou fornecedor, desde que não haja
 * vínculos ativos. Caso haja, a orientação é INATIVAR o cadastro —
 * assim o histórico financeiro e comercial continua consistente.
 */
class DeleteBusinessParty
{
    public function handle(Customer|Supplier $party): void
    {
        if ($party instanceof Customer) {
            $hasActiveContracts = $party->contracts()->whereIn('status', [ContractStatus::Active, ContractStatus::Suspended])->exists();
            $hasOpenReceivables = $party->receivables()->whereIn('status', BillStatus::outstanding())->exists();

            if ($hasActiveContracts || $hasOpenReceivables) {
                throw new BusinessRuleException('Este cliente possui contratos ativos ou contas a receber em aberto. Inative o cadastro em vez de excluí-lo.');
            }
        }

        if ($party instanceof Supplier && $party->payables()->whereIn('status', BillStatus::outstanding())->exists()) {
            throw new BusinessRuleException('Este fornecedor possui contas a pagar em aberto. Inative o cadastro em vez de excluí-lo.');
        }

        $party->delete();
    }
}
