<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\CatalogItemResource;
use App\Models\CatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catálogo (somente leitura). Filtros: ?search= ?type=product|service ?active=1|0
 */
class CatalogItemController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CatalogItem::class);

        $query = CatalogItem::query()
            ->with('category:id,name')
            ->search($request->query('search'))
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')));

        return CatalogItemResource::collection($this->sort($query, $request, ['name', 'code', 'price_cents'], 'name')->paginate($this->perPage($request)));
    }

    public function show(CatalogItem $catalogItem): CatalogItemResource
    {
        $this->authorize('view', $catalogItem);

        return new CatalogItemResource($catalogItem->load('category'));
    }
}
