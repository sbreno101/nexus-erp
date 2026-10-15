<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Ticket;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Muda o status de um chamado, mantendo as datas de resolução e
 * encerramento coerentes (reabrir limpa as datas).
 */
class ChangeTicketStatus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Ticket $ticket, TicketStatus $target, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $target, $reason): Ticket {
            /** @var Ticket $locked */
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw new BusinessRuleException(sprintf('Não é possível mudar o chamado de "%s" para "%s".', $from->label(), $target->label()));
            }

            $locked->status = $target;

            match ($target) {
                TicketStatus::Resolved => $locked->forceFill(['resolved_at' => now()]),
                TicketStatus::Closed, TicketStatus::Cancelled => $locked->forceFill([
                    'closed_at' => now(),
                    'resolved_at' => $locked->resolved_at ?? ($target === TicketStatus::Closed ? now() : null),
                ]),
                TicketStatus::InProgress => $locked->forceFill(['resolved_at' => null, 'closed_at' => null]),
                default => $locked,
            };

            $locked->save();
            $locked->recordStatusChange($from, $target, $reason);
            $this->audit->log("ticket.{$target->value}", $locked, ['status' => $from->value], ['status' => $target->value]);

            $ticket->setRawAttributes($locked->getAttributes(), sync: true);

            return $locked;
        });
    }
}
