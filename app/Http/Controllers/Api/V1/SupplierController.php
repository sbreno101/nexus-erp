<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Parties\DeleteBusinessParty;
use App\Actions\Parties\SaveBusinessParty;
use App\Http\Requests\Customers\SupplierRequest;
use App\Http\Resources\V1\PartyResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query()
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status));

        return PartyResource::collection($this->sort($query, $request, ['name', 'created_at'], 'name')->paginate($this->perPage($request)));
    }

    public function store(SupplierRequest $request, SaveBusinessParty $saveParty): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $saveParty->handle(new Supplier, $request->partyData(), $request->validated('addresses', []), $request->validated('contacts', []));

        return (new PartyResource($supplier->load(['addresses', 'contacts'])))->response()->setStatusCode(201);
    }

    public function show(Supplier $supplier): PartyResource
    {
        $this->authorize('view', $supplier);

        return new PartyResource($supplier->load(['addresses', 'contacts']));
    }

    public function update(SupplierRequest $request, Supplier $supplier, SaveBusinessParty $saveParty): PartyResource
    {
        $this->authorize('update', $supplier);

        $addresses = $request->has('addresses') ? $request->validated('addresses', []) : $supplier->addresses()->get()->toArray();
        $contacts = $request->has('contacts') ? $request->validated('contacts', []) : $supplier->contacts()->get()->toArray();

        $saveParty->handle($supplier, $request->partyData(), $addresses, $contacts);

        return new PartyResource($supplier->load(['addresses', 'contacts']));
    }

    public function destroy(Supplier $supplier, DeleteBusinessParty $deleteParty): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $deleteParty->handle($supplier);

        return response()->json(null, 204);
    }
}
