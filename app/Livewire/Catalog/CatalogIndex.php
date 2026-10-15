<?php

namespace App\Livewire\Catalog;

use App\Enums\CatalogItemType;
use App\Livewire\Concerns\WithDataTable;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listagem do catálogo de produtos e serviços.
 *
 * Os filtros ficam na URL (?tipo=service&situacao=ativo...) para que o
 * usuário possa recarregar a página ou compartilhar o link com os mesmos
 * filtros aplicados.
 */
#[Title('Catálogo')]
class CatalogIndex extends Component
{
    use WithDataTable;

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'categoria', except: '')]
    public string $category = '';

    /**
     * Situação do item: "ativo", "inativo" ou vazio (todos).
     */
    #[Url(as: 'situacao', except: '')]
    public string $situation = '';

    /**
     * Colunas que podem ser usadas no ORDER BY (proteção contra valores
     * arbitrários vindos da URL).
     *
     * @var list<string>
     */
    protected array $sortable = ['code', 'name', 'price_cents'];

    public function mount(): void
    {
        $this->authorize('viewAny', CatalogItem::class);
    }

    /**
     * Exclusão lógica (soft delete): o item some das listagens, mas propostas
     * e contratos antigos continuam apontando para ele.
     */
    public function delete(int $catalogItemId): void
    {
        $catalogItem = CatalogItem::query()->findOrFail($catalogItemId);
        $this->authorize('delete', $catalogItem);

        $catalogItem->delete();

        $this->dispatch('notify', type: 'success', message: 'Item excluído do catálogo.');
    }

    public function render(): View
    {
        $query = CatalogItem::query()
            ->with('category:id,name')
            ->search($this->search)
            ->when(CatalogItemType::tryFrom($this->type), fn ($query, CatalogItemType $type) => $query->where('type', $type))
            ->when($this->category !== '', fn ($query) => $query->where('catalog_category_id', (int) $this->category))
            ->when($this->situation === 'ativo', fn ($query) => $query->where('is_active', true))
            ->when($this->situation === 'inativo', fn ($query) => $query->where('is_active', false));

        // As categorias do filtro acompanham o tipo escolhido (produto/serviço).
        $categories = CatalogCategory::query()
            ->when(CatalogItemType::tryFrom($this->type), fn ($query, CatalogItemType $type) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return view('livewire.catalog.catalog-index', [
            'items' => $this->applySorting($query, 'name')->paginate($this->perPageForCompany()),
            'typeOptions' => CatalogItemType::options(),
            'categories' => $categories,
        ]);
    }
}
