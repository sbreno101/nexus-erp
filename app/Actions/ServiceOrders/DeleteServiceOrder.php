<?php

namespace App\Actions\ServiceOrders;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ServiceOrder;

/**
 * Exclui (soft delete) uma ordem de serviço.
 *
 * Só OS "Aberta" (nada foi executado ainda) ou "Cancelada" podem ser
 * excluídas. OS em execução, aguardando ou concluídas têm histórico de
 * trabalho que precisa ser preservado — nesses casos, cancele a OS.
 */
class DeleteServiceOrder
{
    public function handle(ServiceOrder $order): void
    {
        if (! in_array($order->status, [ServiceOrderStatus::Open, ServiceOrderStatus::Cancelled], true)) {
            throw new BusinessRuleException(sprintf(
                'Não é possível excluir uma OS com status "%s". Apenas OS abertas ou canceladas podem ser excluídas.',
                $order->status->label(),
            ));
        }

        $order->delete();
    }
}
