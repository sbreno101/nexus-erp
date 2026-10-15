<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\SaveTicket;
use App\Http\Requests\Operations\StoreTicketMessageRequest;
use App\Http\Requests\Operations\TicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Chamados via API. Filtros: ?search= ?status= ?priority= ?customer_id=
 */
class TicketController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        $query = Ticket::query()
            ->with(['customer:id,name,trade_name', 'assignee:id,name'])
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->query('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId));

        return TicketResource::collection($this->sort($query, $request, ['number', 'created_at'], 'created_at', 'desc')->paginate($this->perPage($request)));
    }

    public function store(TicketRequest $request, SaveTicket $saveTicket): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $ticket = $saveTicket->create($request->validated());

        return (new TicketResource($ticket->load(['customer', 'assignee'])))->response()->setStatusCode(201);
    }

    public function show(Ticket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        return new TicketResource($ticket->load(['customer', 'assignee', 'messages.user']));
    }

    public function update(TicketRequest $request, Ticket $ticket, SaveTicket $saveTicket): TicketResource
    {
        $this->authorize('update', $ticket);

        $saveTicket->update($ticket, $request->validated());

        return new TicketResource($ticket->load(['customer', 'assignee']));
    }

    public function storeMessage(StoreTicketMessageRequest $request, Ticket $ticket, AddTicketMessage $addMessage): JsonResponse
    {
        $this->authorize('update', $ticket);

        $addMessage->handle($ticket, $request->validated('body'), $request->boolean('is_internal'));

        return (new TicketResource($ticket->fresh()->load(['customer', 'assignee', 'messages.user'])))->response()->setStatusCode(201);
    }
}
