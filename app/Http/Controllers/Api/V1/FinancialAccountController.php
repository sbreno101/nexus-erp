<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\FinancialAccountResource;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinancialAccountController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $query = FinancialAccount::query()->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')));

        return FinancialAccountResource::collection($this->sort($query, $request, ['name', 'current_balance_cents'], 'name')->paginate($this->perPage($request)));
    }

    public function show(FinancialAccount $account): FinancialAccountResource
    {
        $this->authorize('view', $account);

        return new FinancialAccountResource($account);
    }
}
