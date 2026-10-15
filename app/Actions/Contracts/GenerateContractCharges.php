<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Receivable;
use App\Support\Billing\BillingSchedule;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Gera as cobranças recorrentes (contas a receber) dos contratos ativos.
 *
 * Regras:
 *  - Só contratos ATIVOS geram cobranças (suspensos/cancelados/encerrados não).
 *  - A cobrança é gerada com antecedência: quando o próximo vencimento está
 *    dentro da janela "billing_days_in_advance" (configuração da empresa).
 *  - Não gera cobranças com vencimento após o término da vigência.
 *  - IDEMPOTENTE: rodar o processo duas vezes no mesmo dia não duplica nada.
 *    Proteções: (1) o contrato é travado durante a geração; (2) verificamos
 *    se já existe cobrança para a competência; (3) índice UNIQUE parcial
 *    (contract_id, competence) no banco.
 */
class GenerateContractCharges
{
    public function __construct(
        private BillingSchedule $schedule,
        private CurrentCompany $currentCompany,
    ) {}

    /**
     * Processa todos os contratos elegíveis da EMPRESA ATIVA.
     *
     * @return int quantidade de cobranças criadas
     */
    public function forCurrentCompany(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $horizon = $today->addDays($this->currentCompany->getOrFail()->settingsOrDefault()->billing_days_in_advance);
        $created = 0;

        Contract::query()
            ->where('status', ContractStatus::Active)
            ->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', $horizon)
            ->eachById(function (Contract $contract) use ($horizon, &$created): void {
                $created += $this->forContract($contract, $horizon);
            });

        return $created;
    }

    /**
     * Gera, para um contrato, todas as cobranças vencendo até o horizonte informado.
     */
    public function forContract(Contract $contract, CarbonImmutable $horizon): int
    {
        return DB::transaction(function () use ($contract, $horizon): int {
            /** @var Contract $locked */
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->generatesCharges() || $locked->next_billing_date === null) {
                return 0;
            }

            $created = 0;
            $dueDate = CarbonImmutable::parse($locked->next_billing_date);

            while ($dueDate->lte($horizon)) {
                if (! $locked->isInForceOn($dueDate)) {
                    $locked->next_billing_date = null;
                    break;
                }

                if ($this->createCharge($locked, $dueDate)) {
                    $created++;
                }

                $dueDate = $this->schedule->advance($dueDate, $locked->billing_frequency, $locked->billing_day);
                $locked->next_billing_date = $dueDate;
            }

            $locked->saveQuietly();
            $contract->setRawAttributes($locked->getAttributes(), sync: true);

            return $created;
        });
    }

    /**
     * Cria a cobrança de uma competência, se ainda não existir.
     */
    private function createCharge(Contract $contract, CarbonImmutable $dueDate): bool
    {
        $competence = $this->schedule->competenceFor($dueDate);

        $alreadyExists = Receivable::query()
            ->where('contract_id', $contract->id)
            ->whereDate('competence', $competence)
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        try {
            // Savepoint: se o índice UNIQUE barrar, desfaz só esta inserção.
            DB::transaction(fn () => Receivable::create([
                'customer_id' => $contract->customer_id,
                'contract_id' => $contract->id,
                'financial_category_id' => $contract->financial_category_id,
                'description' => sprintf('%s — %s (competência %s)', $contract->number, $contract->title, $competence->format('m/Y')),
                'amount_cents' => $contract->amount_cents,
                'issue_date' => CarbonImmutable::today()->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'competence' => $competence->toDateString(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
