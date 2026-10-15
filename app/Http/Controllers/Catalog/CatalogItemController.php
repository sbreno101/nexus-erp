<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\CatalogItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogItemRequest;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Cadastro de itens do catálogo (a listagem fica no Livewire CatalogIndex).
 *
 * Não há regra de negócio além da validação, por isso o Controller grava
 * direto no Model. O histórico de preços vem de graça da auditoria
 * automática (trait Auditable do CatalogItem).
 */
class CatalogItemController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', CatalogItem::class);

        return view('catalog.create', [
            'catalogItem' => new CatalogItem(['type' => CatalogItemType::Service, 'unit' => 'UN', 'is_active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(CatalogItemRequest $request): RedirectResponse
    {
        $this->authorize('create', CatalogItem::class);

        // company_id é preenchido pelo trait BelongsToCompany.
        $catalogItem = CatalogItem::create($request->validated());

        return redirect()->route('catalog.show', $catalogItem)->with('success', 'Item cadastrado no catálogo.');
    }

    public function show(CatalogItem $catalogItem): View
    {
        $this->authorize('view', $catalogItem);

        // loadCount: contagens em uma única consulta, sem carregar os itens.
        $catalogItem->load('category')->loadCount(['proposalItems', 'contractItems']);

        return view('catalog.show', [
            'catalogItem' => $catalogItem,
            'auditLogs' => $catalogItem->auditLogs()->with('user')->limit(30)->get(),
        ]);
    }

    public function edit(CatalogItem $catalogItem): View
    {
        $this->authorize('update', $catalogItem);

        return view('catalog.edit', [
            'catalogItem' => $catalogItem,
            'categories' => $this->categories(),
        ]);
    }

    public function update(CatalogItemRequest $request, CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorize('update', $catalogItem);

        $catalogItem->update($request->validated());

        return redirect()->route('catalog.show', $catalogItem)->with('success', 'Item atualizado.');
    }

    /**
     * Exclusão lógica: propostas e contratos que usam o item continuam
     * exibindo seus dados normalmente.
     */
    public function destroy(CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorize('delete', $catalogItem);

        $catalogItem->delete();

        return redirect()->route('catalog.index')->with('success', 'Item excluído do catálogo.');
    }

    /**
     * Categorias de todos os tipos; o formulário mostra apenas as do tipo
     * selecionado (filtro feito no navegador com Alpine.js).
     *
     * @return Collection<int, CatalogCategory>
     */
    private function categories(): Collection
    {
        return CatalogCategory::query()->orderBy('name')->get(['id', 'name', 'type']);
    }
}
