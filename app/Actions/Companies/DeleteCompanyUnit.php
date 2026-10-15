<?php

namespace App\Actions\Companies;

use App\Exceptions\BusinessRuleException;
use App\Models\CompanyUnit;

/**
 * Exclui (soft delete) uma unidade da empresa ativa.
 *
 * Uma unidade em uso (usuários lotados nela ou contratos vinculados) não
 * pode ser excluída: o histórico perderia a referência. Nesses casos o
 * caminho é marcá-la como inativa.
 */
class DeleteCompanyUnit
{
    public function handle(CompanyUnit $unit): void
    {
        if ($unit->memberships()->exists()) {
            throw new BusinessRuleException('Há usuários vinculados a esta unidade. Altere a unidade deles ou apenas inative a unidade.');
        }

        if ($unit->contracts()->exists()) {
            throw new BusinessRuleException('Há contratos vinculados a esta unidade. Inative a unidade em vez de excluí-la.');
        }

        $unit->delete();
    }
}
