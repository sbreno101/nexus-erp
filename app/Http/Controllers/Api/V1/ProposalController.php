<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Proposals\ChangeProposalStatus;
use App\Actions\Proposals\ConvertProposalToContract;
use App\Actions\Proposals\SaveProposal;
use App\Enums\ProposalStatus;
use App\Http\Requests\Commercial\ChangeStatusRequest;
use App\Http\Requests\Commercial\ConvertProposalRequest;
use App\Http\Requests\Commercial\ProposalRequest;
use App\Http\Resources\V1\ContractResource;
use App\Http\Resources\V1\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Propostas via API. Itens enviados com unit_price_cents/discount_cents
 * (inteiros) ou unit_price/discount (texto "1.234,56"); totais calculados no servidor.
 *
 * Filtros: ?search= ?status= ?customer_id=
 */
class ProposalController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Proposal::class);

        $query = Proposal::query()
            ->with('customer:id,name,trade_name')
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId));

        return ProposalResource::collection($this->sort($query, $request, ['number', 'issue_date', 'total_cents'], 'issue_date', 'desc')->paginate($this->perPage($request)));
    }

    public function store(ProposalRequest $request, SaveProposal $saveProposal): JsonResponse
    {
        $this->authorize('create', Proposal::class);

        $proposal = $saveProposal->create($request->proposalData());

        return (new ProposalResource($proposal->load(['customer', 'items'])))->response()->setStatusCode(201);
    }

    public function show(Proposal $proposal): ProposalResource
    {
        $this->authorize('view', $proposal);

        return new ProposalResource($proposal->load(['customer', 'items', 'contract']));
    }

    public function update(ProposalRequest $request, Proposal $proposal, SaveProposal $saveProposal): ProposalResource
    {
        $this->authorize('update', $proposal);

        $saveProposal->update($proposal, $request->proposalData());

        return new ProposalResource($proposal->load(['customer', 'items']));
    }

    /**
     * POST /proposals/{id}/status  {"status": "sent|approved|rejected|cancelled|draft", "reason": "..."}
     */
    public function changeStatus(ChangeStatusRequest $request, Proposal $proposal, ChangeProposalStatus $changeStatus): ProposalResource
    {
        $this->authorize('changeStatus', $proposal);
        $request->validate(['status' => [Rule::enum(ProposalStatus::class)]]);

        $changeStatus->handle($proposal, ProposalStatus::from($request->string('status')->toString()), $request->input('reason'));

        return new ProposalResource($proposal->fresh()->load(['customer', 'items']));
    }

    public function convert(ConvertProposalRequest $request, Proposal $proposal, ConvertProposalToContract $convert): JsonResponse
    {
        $this->authorize('convert', $proposal);

        $contract = $convert->handle($proposal, $request->validated());

        return (new ContractResource($contract->fresh()->load(['customer', 'items'])))->response()->setStatusCode(201);
    }
}
