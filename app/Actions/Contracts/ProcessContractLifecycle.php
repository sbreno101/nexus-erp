<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;

/**
 * Rotina diária de vigência: contratos cujo término já passou são
 * renovados automaticamente (se auto_renew) ou encerrados.
 */
class ProcessContractLifecycle
{
    public function __construct(
        private RenewContract $renewContract,
        private ChangeContractStatus $changeStatus,
    ) {}

    /**
     * @return array{renewed: int, finished: int}
     */
    public function forCurrentCompany(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $result = ['renewed' => 0, 'finished' => 0];

        Contract::query()
            ->whereIn('status', [ContractStatus::Active, ContractStatus::Suspended])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->eachById(function (Contract $contract) use (&$result, $today): void {
                if ($contract->auto_renew) {
                    // Renova quantas vezes forem necessárias para cobrir a data atual.
                    do {
                        $this->renewContract->handle($contract);
                    } while (CarbonImmutable::parse($contract->end_date)->lt($today));

                    $result['renewed']++;

                    return;
                }

                $this->changeStatus->handle($contract, ContractStatus::Finished, 'Encerrado automaticamente ao fim da vigência.');
                $result['finished']++;
            });

        return $result;
    }
}
