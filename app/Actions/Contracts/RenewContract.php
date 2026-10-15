<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Contract;
use App\Services\AuditLogger;
use App\Support\Billing\BillingSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Renova a vigência de um contrato com data de término, estendendo-a por
 * N meses (padrão: renewal_months do contrato). O histórico guarda a data
 * de término anterior.
 */
class RenewContract
{
    public function __construct(
        private AuditLogger $audit,
        private BillingSchedule $schedule,
    ) {}

    public function handle(Contract $contract, ?int $months = null): Contract
    {
        return DB::transaction(function () use ($contract, $months): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ContractStatus::Active, ContractStatus::Suspended], true)) {
                throw new BusinessRuleException('Somente contratos ativos ou suspensos podem ser renovados.');
            }

            if ($locked->end_date === null) {
                throw new BusinessRuleException('Este contrato tem prazo indeterminado e não precisa de renovação.');
            }

            $months ??= $locked->renewal_months;

            if ($months < 1 || $months > 120) {
                throw new BusinessRuleException('O período de renovação deve estar entre 1 e 120 meses.');
            }

            $previousEnd = CarbonImmutable::parse($locked->end_date);
            $newEnd = $previousEnd->addMonthsNoOverflow($months);

            $locked->forceFill([
                'end_date' => $newEnd->toDateString(),
                'renewed_at' => now(),
                'next_billing_date' => $this->resumeBillingDate($locked)?->toDateString(),
            ])->save();
            $locked->recordStatusChange($locked->status, $locked->status, sprintf(
                'Renovado por %d meses: término de %s para %s.',
                $months,
                $previousEnd->format('d/m/Y'),
                $newEnd->format('d/m/Y'),
            ));

            $this->audit->log('contract.renewed', $locked, ['end_date' => $previousEnd->toDateString()], ['end_date' => $newEnd->toDateString()]);

            $contract->setRawAttributes($locked->getAttributes(), sync: true);

            return $locked;
        });
    }

    /**
     * Ao atingir o fim da vigência, a geração de cobranças zera next_billing_date.
     * Na renovação de um contrato ATIVO, a cobrança é retomada a partir do
     * vencimento seguinte à última cobrança gerada (sem duplicar nem pular períodos).
     */
    private function resumeBillingDate(Contract $contract): ?CarbonImmutable
    {
        if ($contract->next_billing_date !== null) {
            return CarbonImmutable::parse($contract->next_billing_date);
        }

        if ($contract->status !== ContractStatus::Active) {
            return null;
        }

        $lastDueDate = $contract->receivables()->max('due_date');

        return $lastDueDate === null
            ? $contract->firstBillingDate()
            : $this->schedule->advance(CarbonImmutable::parse($lastDueDate), $contract->billing_frequency, $contract->billing_day);
    }
}
