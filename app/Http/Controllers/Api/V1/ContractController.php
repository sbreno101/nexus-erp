<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contracts\ChangeContractStatus;
use App\Enums\ContractStatus;
use App\Http\Requests\Commercial\ChangeStatusRequest;
use App\Http\Resources\V1\ContractResource;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Contratos via API. Filtros: ?search= ?status= ?customer_id=
 */
class ContractController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contract::class);

        $query = Contract::query()
            ->with('customer:id,name,trade_name')
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId));

        return ContractResource::collection($this->sort($query, $request, ['number', 'start_date', 'amount_cents'], 'number', 'desc')->paginate($this->perPage($request)));
    }

    public function show(Contract $contract): ContractResource
    {
        $this->authorize('view', $contract);

        return new ContractResource($contract->load(['customer', 'items']));
    }

    /**
     * POST /contracts/{id}/status  {"status": "active|suspended|cancelled|finished", "reason": "..."}
     */
    public function changeStatus(ChangeStatusRequest $request, Contract $contract, ChangeContractStatus $changeStatus): ContractResource
    {
        $this->authorize('manage', $contract);
        $request->validate(['status' => [Rule::enum(ContractStatus::class)]]);

        $changeStatus->handle($contract, ContractStatus::from($request->string('status')->toString()), $request->input('reason'));

        return new ContractResource($contract->fresh()->load(['customer', 'items']));
    }
}
