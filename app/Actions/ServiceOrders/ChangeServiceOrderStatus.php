<?php

namespace App\Actions\ServiceOrders;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceOrder;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Muda o status de uma OS (iniciar, pausar, concluir, cancelar, reabrir).
 *
 * Encerramento: concluir exige o registro da solução aplicada; a data de
 * conclusão é preenchida automaticamente e limpa se a OS for reaberta.
 */
class ChangeServiceOrderStatus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(ServiceOrder $order, ServiceOrderStatus $target, ?string $note = null): ServiceOrder
    {
        return DB::transaction(function () use ($order, $target, $note): ServiceOrder {
            /** @var ServiceOrder $locked */
            $locked = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw new BusinessRuleException(sprintf('Não é possível mudar a OS de "%s" para "%s".', $from->label(), $target->label()));
            }

            if (in_array($target, [ServiceOrderStatus::Completed, ServiceOrderStatus::Cancelled], true) && blank($note)) {
                throw new BusinessRuleException($target === ServiceOrderStatus::Completed
                    ? 'Descreva a solução aplicada para concluir a ordem de serviço.'
                    : 'Informe o motivo do cancelamento.');
            }

            $locked->status = $target;

            match ($target) {
                ServiceOrderStatus::InProgress => $locked->forceFill([
                    'started_at' => $locked->started_at ?? now(),
                    'completed_at' => null,
                ]),
                ServiceOrderStatus::Completed => $locked->forceFill(['completed_at' => now(), 'resolution' => $note]),
                default => $locked,
            };

            $locked->save();
            $locked->recordStatusChange($from, $target, $note);
            $this->audit->log("service_order.{$target->value}", $locked, ['status' => $from->value], ['status' => $target->value]);

            $order->setRawAttributes($locked->getAttributes(), sync: true);

            return $locked;
        });
    }
}
