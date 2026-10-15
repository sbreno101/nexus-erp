<?php

namespace App\Actions\ServiceOrders;

use App\Enums\ServiceOrderStatus;
use App\Events\WorkItemAssigned;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Abre ou atualiza uma ordem de serviço. Quando o responsável muda,
 * dispara WorkItemAssigned (que notifica a pessoa designada).
 */
class SaveServiceOrder
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ServiceOrder
    {
        $order = DB::transaction(function () use ($data): ServiceOrder {
            $order = ServiceOrder::create([
                ...$data,
                'number' => $this->numbers->next(DocumentNumberGenerator::SERVICE_ORDER),
                'status' => ServiceOrderStatus::Open,
                'created_by' => Auth::id(),
            ]);

            $order->recordStatusChange(null, ServiceOrderStatus::Open, 'Ordem de serviço aberta.');

            return $order;
        });

        $this->notifyAssignee($order, null);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ServiceOrder $order, array $data): ServiceOrder
    {
        $previousAssignee = $order->assigned_to;
        $order->update($data);
        $this->notifyAssignee($order, $previousAssignee);

        return $order;
    }

    private function notifyAssignee(ServiceOrder $order, ?int $previousAssignee): void
    {
        if ($order->assigned_to !== null && $order->assigned_to !== $previousAssignee) {
            /** @var User|null $actor */
            $actor = Auth::user();
            WorkItemAssigned::dispatch($order, User::findOrFail($order->assigned_to), $actor);
        }
    }
}
