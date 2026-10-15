<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\WorkItemAssigned;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Abre ou atualiza um chamado de atendimento.
 */
class SaveTicket
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Ticket
    {
        $ticket = DB::transaction(function () use ($data): Ticket {
            $ticket = Ticket::create([
                ...$data,
                'number' => $this->numbers->next(DocumentNumberGenerator::TICKET),
                'status' => TicketStatus::Open,
                'created_by' => Auth::id(),
            ]);

            $ticket->recordStatusChange(null, TicketStatus::Open, 'Chamado aberto.');

            return $ticket;
        });

        $this->notifyAssignee($ticket, null);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data): Ticket
    {
        $previousAssignee = $ticket->assigned_to;
        $ticket->update($data);
        $this->notifyAssignee($ticket, $previousAssignee);

        return $ticket;
    }

    private function notifyAssignee(Ticket $ticket, ?int $previousAssignee): void
    {
        if ($ticket->assigned_to !== null && $ticket->assigned_to !== $previousAssignee) {
            /** @var User|null $actor */
            $actor = Auth::user();
            WorkItemAssigned::dispatch($ticket, User::findOrFail($ticket->assigned_to), $actor);
        }
    }
}
