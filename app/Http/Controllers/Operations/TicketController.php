<?php

namespace App\Http\Controllers\Operations;

use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\ChangeTicketStatus;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\SaveTicket;
use App\Enums\Priority;
use App\Enums\TicketChannel;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\ProvidesOperationFormOptions;
use App\Http\Requests\Operations\ChangeTicketStatusRequest;
use App\Http\Requests\Operations\StoreTicketMessageRequest;
use App\Http\Requests\Operations\TicketRequest;
use App\Models\Contract;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chamados de atendimento: formulário, conversa (mensagens), mudança de
 * status. A listagem fica no Livewire TicketIndex.
 */
class TicketController extends Controller
{
    use ProvidesOperationFormOptions;

    public function __construct(private CurrentCompany $currentCompany) {}

    /**
     * Aceita ?customer_id=, ?contract_id= e ?service_order_id= para abrir o
     * chamado já preenchido (ex.: botão "Abrir chamado" na tela da OS).
     * Se o cliente não vier, ele é deduzido do contrato ou da OS.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Ticket::class);

        $customerId = $request->integer('customer_id') ?: null;
        $contractId = $request->integer('contract_id') ?: null;
        $serviceOrderId = $request->integer('service_order_id') ?: null;

        // As consultas já são filtradas pela empresa ativa (global scope).
        if ($contractId !== null) {
            $contract = Contract::query()->find($contractId, ['id', 'customer_id']);
            $contractId = $contract?->id;
            $customerId ??= $contract?->customer_id;
        }

        if ($serviceOrderId !== null) {
            $serviceOrder = ServiceOrder::query()->find($serviceOrderId, ['id', 'customer_id', 'contract_id']);
            $serviceOrderId = $serviceOrder?->id;
            $customerId ??= $serviceOrder?->customer_id;
            $contractId ??= $serviceOrder?->contract_id;
        }

        $ticket = new Ticket([
            'customer_id' => $customerId,
            'contract_id' => $contractId,
            'service_order_id' => $serviceOrderId,
            'priority' => Priority::Medium,
            'channel' => TicketChannel::Email,
        ]);

        return view('tickets.create', [
            'ticket' => $ticket,
            ...$this->formData($ticket),
        ]);
    }

    public function store(TicketRequest $request, SaveTicket $saveTicket): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $ticket = $saveTicket->create($request->validated());

        return redirect()->route('tickets.show', $ticket)->with('success', "Chamado {$ticket->number} aberto.");
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        // Eager loading de tudo que a tela exibe (preventLazyLoading está ativo).
        $ticket->load([
            'customer', 'contract', 'serviceOrder', 'category', 'assignee', 'creator',
            'messages.user',
            'statusChanges.user',
            'attachments.uploader',
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return view('tickets.edit', [
            'ticket' => $ticket,
            ...$this->formData($ticket),
        ]);
    }

    public function update(TicketRequest $request, Ticket $ticket, SaveTicket $saveTicket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $saveTicket->update($ticket, $request->validated());

        return redirect()->route('tickets.show', $ticket)->with('success', 'Chamado atualizado.');
    }

    public function destroy(Ticket $ticket, DeleteTicket $deleteTicket): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $deleteTicket->handle($ticket);

        return redirect()->route('tickets.index')->with('success', "Chamado {$ticket->number} excluído.");
    }

    public function changeStatus(ChangeTicketStatusRequest $request, Ticket $ticket, ChangeTicketStatus $changeStatus): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $target = $request->targetStatus();
        $changeStatus->handle($ticket, $target, $request->validated('reason'));

        return redirect()->route('tickets.show', $ticket)->with('success', "Status alterado para \"{$target->label()}\".");
    }

    /**
     * Resposta ao cliente ou comentário interno. A Action cuida dos efeitos
     * colaterais (primeira resposta, mudar "Aberto" para "Em atendimento").
     */
    public function storeMessage(StoreTicketMessageRequest $request, Ticket $ticket, AddTicketMessage $addMessage): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $isInternal = $request->boolean('is_internal');
        $addMessage->handle($ticket, $request->validated('body'), $isInternal);

        return redirect()->route('tickets.show', $ticket)->with('success', $isInternal ? 'Comentário interno registrado.' : 'Resposta registrada.');
    }

    /**
     * Opções dos selects do formulário (compartilhado entre criar e editar).
     *
     * @return array{customers: array<int, string>, contracts: Collection<int, Contract>, serviceOrders: Collection<int, ServiceOrder>, categories: array<int, string>, users: array<int, string>, priorityOptions: array<string, string>, channelOptions: array<string, string>}
     */
    private function formData(Ticket $ticket): array
    {
        return [
            'customers' => $this->customerOptions($ticket->customer_id),
            'contracts' => $this->contractOptions(),
            'serviceOrders' => $this->serviceOrderOptions($ticket->service_order_id),
            'categories' => TicketCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::optionsForCompany($this->currentCompany->id()),
            'priorityOptions' => Priority::options(),
            'channelOptions' => TicketChannel::options(),
        ];
    }
}
