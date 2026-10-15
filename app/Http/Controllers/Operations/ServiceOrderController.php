<?php

namespace App\Http\Controllers\Operations;

use App\Actions\ServiceOrders\ChangeServiceOrderStatus;
use App\Actions\ServiceOrders\DeleteServiceOrder;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Enums\Priority;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\ProvidesOperationFormOptions;
use App\Http\Requests\Operations\ChangeServiceOrderStatusRequest;
use App\Http\Requests\Operations\ServiceOrderRequest;
use App\Http\Requests\Operations\StoreServiceOrderActivityRequest;
use App\Models\Contract;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ordens de serviço: formulário, detalhes, mudança de status e registro de
 * atividades. A listagem fica no Livewire ServiceOrderIndex.
 *
 * As regras de negócio (numeração, histórico de status, notificação do
 * responsável, quem pode ser excluído) ficam nas Actions.
 */
class ServiceOrderController extends Controller
{
    use ProvidesOperationFormOptions;

    public function __construct(private CurrentCompany $currentCompany) {}

    /**
     * Aceita ?customer_id= e ?contract_id= para abrir a OS já preenchida
     * (ex.: botão "Nova OS" na tela do contrato). Se vier só o contrato,
     * o cliente é deduzido dele.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', ServiceOrder::class);

        $contractId = $request->integer('contract_id') ?: null;
        $customerId = $request->integer('customer_id') ?: null;

        if ($contractId !== null) {
            // A consulta já é filtrada pela empresa ativa (global scope).
            $contract = Contract::query()->find($contractId, ['id', 'customer_id']);
            $contractId = $contract?->id;
            $customerId ??= $contract?->customer_id;
        }

        $serviceOrder = new ServiceOrder([
            'customer_id' => $customerId,
            'contract_id' => $contractId,
            'priority' => Priority::Medium,
        ]);

        return view('service-orders.create', [
            'serviceOrder' => $serviceOrder,
            ...$this->formData($serviceOrder),
        ]);
    }

    public function store(ServiceOrderRequest $request, SaveServiceOrder $saveServiceOrder): RedirectResponse
    {
        $this->authorize('create', ServiceOrder::class);

        $serviceOrder = $saveServiceOrder->create($request->validated());

        return redirect()->route('service-orders.show', $serviceOrder)->with('success', "Ordem de serviço {$serviceOrder->number} aberta.");
    }

    public function show(ServiceOrder $serviceOrder): View
    {
        $this->authorize('view', $serviceOrder);

        // Eager loading de tudo que a tela exibe (preventLazyLoading está ativo).
        $serviceOrder->load([
            'customer', 'contract', 'service', 'assignee', 'creator',
            'activities.user',
            'tickets' => fn ($query) => $query->latest()->limit(20),
            'statusChanges.user',
            'attachments.uploader',
        ]);

        return view('service-orders.show', [
            'serviceOrder' => $serviceOrder,
            'totalMinutes' => $serviceOrder->totalMinutesSpent(),
        ]);
    }

    public function edit(ServiceOrder $serviceOrder): View
    {
        $this->authorize('update', $serviceOrder);

        return view('service-orders.edit', [
            'serviceOrder' => $serviceOrder,
            ...$this->formData($serviceOrder),
        ]);
    }

    public function update(ServiceOrderRequest $request, ServiceOrder $serviceOrder, SaveServiceOrder $saveServiceOrder): RedirectResponse
    {
        $this->authorize('update', $serviceOrder);

        $saveServiceOrder->update($serviceOrder, $request->validated());

        return redirect()->route('service-orders.show', $serviceOrder)->with('success', 'Ordem de serviço atualizada.');
    }

    public function destroy(ServiceOrder $serviceOrder, DeleteServiceOrder $deleteServiceOrder): RedirectResponse
    {
        $this->authorize('delete', $serviceOrder);

        $deleteServiceOrder->handle($serviceOrder);

        return redirect()->route('service-orders.index')->with('success', "Ordem de serviço {$serviceOrder->number} excluída.");
    }

    /**
     * Iniciar, pausar, concluir, cancelar ou reabrir. A Action valida se a
     * transição é permitida e exige o motivo/solução quando necessário.
     */
    public function changeStatus(ChangeServiceOrderStatusRequest $request, ServiceOrder $serviceOrder, ChangeServiceOrderStatus $changeStatus): RedirectResponse
    {
        $this->authorize('update', $serviceOrder);

        $target = $request->targetStatus();
        $changeStatus->handle($serviceOrder, $target, $request->validated('reason'));

        return redirect()->route('service-orders.show', $serviceOrder)->with('success', "Status alterado para \"{$target->label()}\".");
    }

    public function storeActivity(StoreServiceOrderActivityRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        $this->authorize('update', $serviceOrder);

        if (! $serviceOrder->status->acceptsActivities()) {
            throw new BusinessRuleException('Não é possível registrar atividades em uma OS '.mb_strtolower($serviceOrder->status->label()).'. Reabra a OS antes.');
        }

        $serviceOrder->activities()->create([
            'description' => $request->validated('description'),
            'minutes_spent' => $request->validated('minutes_spent'),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('service-orders.show', $serviceOrder)->with('success', 'Atividade registrada.');
    }

    /**
     * Opções dos selects do formulário (compartilhado entre criar e editar).
     *
     * @return array{customers: array<int, string>, contracts: Collection<int, Contract>, services: array<int, string>, users: array<int, string>, priorityOptions: array<string, string>}
     */
    private function formData(ServiceOrder $serviceOrder): array
    {
        return [
            'customers' => $this->customerOptions($serviceOrder->customer_id),
            'contracts' => $this->contractOptions(),
            'services' => $this->serviceOptions($serviceOrder->catalog_item_id),
            'users' => User::optionsForCompany($this->currentCompany->id()),
            'priorityOptions' => Priority::options(),
        ];
    }
}
