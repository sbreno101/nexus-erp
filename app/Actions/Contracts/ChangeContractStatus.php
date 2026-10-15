<?php

namespace App\Actions\Contracts;

use App\Enums\BillStatus;
use App\Enums\ContractStatus;
use App\Events\ContractStatusChanged;
use App\Exceptions\BusinessRuleException;
use App\Models\Contract;
use App\Services\AuditLogger;
use App\Support\Billing\BillingSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Muda o status de um contrato aplicando os efeitos colaterais de cada transição.
 *
 *  - Ativar:   define a próxima data de cobrança (passa a gerar cobranças).
 *  - Suspender: interrompe a geração de novas cobranças (as existentes permanecem).
 *  - Reativar: retoma a partir do próximo vencimento futuro (o período
 *              suspenso NÃO é cobrado retroativamente).
 *  - Cancelar: interrompe a geração e cancela cobranças FUTURAS ainda não pagas.
 *  - Encerrar: fim natural da vigência; interrompe a geração.
 */
class ChangeContractStatus
{
    public function __construct(
        private AuditLogger $audit,
        private BillingSchedule $schedule,
    ) {}

    public function handle(Contract $contract, ContractStatus $target, ?string $reason = null): Contract
    {
        $from = $contract->status;

        $updated = DB::transaction(function () use ($contract, $target, $reason, $from): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($target)) {
                throw new BusinessRuleException(sprintf(
                    'Transição inválida: um contrato "%s" não pode passar para "%s".',
                    $locked->status->label(),
                    $target->label(),
                ));
            }

            if ($target === ContractStatus::Cancelled && blank($reason)) {
                throw new BusinessRuleException('Informe o motivo do cancelamento.');
            }

            match ($target) {
                ContractStatus::Active => $this->activate($locked),
                ContractStatus::Suspended => $locked->forceFill(['suspended_at' => now()]),
                ContractStatus::Cancelled => $this->cancel($locked, (string) $reason),
                ContractStatus::Finished => $locked->forceFill(['finished_at' => now(), 'next_billing_date' => null]),
                ContractStatus::Draft => $locked,
            };

            $locked->status = $target;
            $locked->save();

            $locked->recordStatusChange($from, $target, $reason);
            $this->audit->log("contract.{$target->value}", $locked, ['status' => $from->value], ['status' => $target->value, 'reason' => $reason]);

            return $locked;
        });

        $contract->setRawAttributes($updated->getAttributes(), sync: true);
        ContractStatusChanged::dispatch($updated, $from, $target);

        return $updated;
    }

    private function activate(Contract $contract): void
    {
        if ($contract->amount_cents <= 0) {
            throw new BusinessRuleException('Defina o valor do contrato antes de ativá-lo.');
        }

        $today = CarbonImmutable::today();
        $next = $contract->next_billing_date
            ? CarbonImmutable::parse($contract->next_billing_date)
            : $contract->firstBillingDate();

        // Na reativação após suspensão, pula vencimentos que ficaram no passado.
        if ($contract->status === ContractStatus::Suspended) {
            while ($next->lt($today)) {
                $next = $this->schedule->advance($next, $contract->billing_frequency, $contract->billing_day);
            }
        }

        $contract->forceFill([
            'activated_at' => $contract->activated_at ?? now(),
            'suspended_at' => null,
            'next_billing_date' => $next->toDateString(),
            'signed_at' => $contract->signed_at ?? $today->toDateString(),
        ]);
    }

    private function cancel(Contract $contract, string $reason): void
    {
        $contract->forceFill([
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'next_billing_date' => null,
        ]);

        // Cobranças futuras (vencimento a partir de hoje) e sem pagamento são canceladas.
        $contract->receivables()
            ->where('status', BillStatus::Open)
            ->where('paid_cents', 0)
            ->whereDate('due_date', '>=', today())
            ->get()
            ->each(fn ($receivable) => $receivable->forceFill([
                'status' => BillStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => "Cancelamento do contrato {$contract->number}: {$reason}",
            ])->save());
    }
}
