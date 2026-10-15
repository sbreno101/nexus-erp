<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Ticket;

/**
 * Exclui (soft delete) um chamado.
 *
 * Só é permitido enquanto o chamado não teve conversa com o cliente:
 *   - status "Aberto" ou "Cancelado"; e
 *   - nenhuma mensagem pública (comentários internos não contam).
 * Chamados atendidos fazem parte do histórico do cliente e dos indicadores
 * de atendimento — nesses casos, cancele ou encerre o chamado.
 */
class DeleteTicket
{
    public function handle(Ticket $ticket): void
    {
        if (! in_array($ticket->status, [TicketStatus::Open, TicketStatus::Cancelled], true)) {
            throw new BusinessRuleException(sprintf(
                'Não é possível excluir um chamado com status "%s". Apenas chamados abertos ou cancelados podem ser excluídos.',
                $ticket->status->label(),
            ));
        }

        if ($ticket->messages()->where('is_internal', false)->exists()) {
            throw new BusinessRuleException('Este chamado já possui respostas ao cliente e não pode ser excluído. Cancele-o ou encerre-o.');
        }

        $ticket->delete();
    }
}
