<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Http\Requests\Operations\ServiceOrderRequest;
use App\Http\Resources\V1\ServiceOrderResource;
use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Ordens de serviço via API. Filtros: ?search= ?status= ?priority= ?assigned_to=
 */
class ServiceOrderController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceOrder::class);

        $query = ServiceOrder::query()
            ->with(['customer:id,name,trade_name', 'assignee:id,name'])
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->query('assigned_to'), fn ($query, $userId) => $query->where('assigned_to', $userId));

        return ServiceOrderResource::collection($this->sort($query, $request, ['number', 'due_date', 'created_at'], 'created_at', 'desc')->paginate($this->perPage($request)));
    }

    public function store(ServiceOrderRequest $request, SaveServiceOrder $saveServiceOrder): JsonResponse
    {
        $this->authorize('create', ServiceOrder::class);

        $order = $saveServiceOrder->create($request->validated());

        return (new ServiceOrderResource($order->load(['customer', 'assignee'])))->response()->setStatusCode(201);
    }

    public function show(ServiceOrder $serviceOrder): ServiceOrderResource
    {
        $this->authorize('view', $serviceOrder);

        return new ServiceOrderResource($serviceOrder->load(['customer', 'assignee']));
    }

    public function update(ServiceOrderRequest $request, ServiceOrder $serviceOrder, SaveServiceOrder $saveServiceOrder): ServiceOrderResource
    {
        $this->authorize('update', $serviceOrder);

        $saveServiceOrder->update($serviceOrder, $request->validated());

        return new ServiceOrderResource($serviceOrder->load(['customer', 'assignee']));
    }
}
