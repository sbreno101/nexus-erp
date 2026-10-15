<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Parties\DeleteBusinessParty;
use App\Actions\Parties\SaveBusinessParty;
use App\Http\Requests\Customers\CustomerRequest;
use App\Http\Resources\V1\PartyResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Clientes via API. Mesmas regras da tela web (CustomerRequest + SaveBusinessParty).
 *
 * Filtros: ?search= ?status=active|inactive|blocked ?type=individual|company
 */
class CustomerController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()
            ->with('owner:id,name')
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type));

        return PartyResource::collection($this->sort($query, $request, ['name', 'created_at', 'document'], 'name')->paginate($this->perPage($request)));
    }

    public function store(CustomerRequest $request, SaveBusinessParty $saveParty): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $saveParty->handle(new Customer, $request->partyData(), $request->validated('addresses', []), $request->validated('contacts', []));

        return (new PartyResource($customer->load(['owner', 'addresses', 'contacts'])))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): PartyResource
    {
        $this->authorize('view', $customer);

        return new PartyResource($customer->load(['owner', 'addresses', 'contacts']));
    }

    public function update(CustomerRequest $request, Customer $customer, SaveBusinessParty $saveParty): PartyResource
    {
        $this->authorize('update', $customer);

        // Na API, endereços/contatos só são substituídos se enviados.
        $addresses = $request->has('addresses') ? $request->validated('addresses', []) : $customer->addresses()->get()->toArray();
        $contacts = $request->has('contacts') ? $request->validated('contacts', []) : $customer->contacts()->get()->toArray();

        $saveParty->handle($customer, $request->partyData(), $addresses, $contacts);

        return new PartyResource($customer->load(['owner', 'addresses', 'contacts']));
    }

    public function destroy(Customer $customer, DeleteBusinessParty $deleteParty): JsonResponse
    {
        $this->authorize('delete', $customer);

        $deleteParty->handle($customer);

        return response()->json(null, 204);
    }
}
