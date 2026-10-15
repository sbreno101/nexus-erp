<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona uma mensagem (resposta ao cliente) ou comentário interno.
 *
 * - A primeira resposta pública registra first_response_at (indicador de
 *   tempo de primeira resposta no relatório de produtividade).
 * - Responder um chamado "Aberto" o coloca automaticamente "Em atendimento".
 */
class AddTicketMessage
{
    public function __construct(private ChangeTicketStatus $changeStatus) {}

    public function handle(Ticket $ticket, string $body, bool $isInternal = false): TicketMessage
    {
        if ($ticket->status->isFinal()) {
            throw new BusinessRuleException('Não é possível adicionar mensagens a um chamado encerrado ou cancelado.');
        }

        return DB::transaction(function () use ($ticket, $body, $isInternal): TicketMessage {
            $message = $ticket->messages()->create([
                'user_id' => Auth::id(),
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            if (! $isInternal && $ticket->first_response_at === null) {
                $ticket->forceFill(['first_response_at' => now()])->save();
            }

            if (! $isInternal && $ticket->status === TicketStatus::Open) {
                $this->changeStatus->handle($ticket, TicketStatus::InProgress, 'Primeira resposta enviada.');
            }

            return $message;
        });
    }
}
