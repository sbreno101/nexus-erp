<?php

namespace App\Livewire\Catalog;

use App\Enums\CatalogItemType;
use App\Models\CatalogCategory;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gerenciamento das categorias do catálogo (criar, renomear e excluir),
 * tudo na mesma tela, sem recarregar a página.
 *
 * O nome da categoria é único por empresa + tipo: pode existir uma categoria
 * "Manutenção" de produtos e outra de serviços, mas não duas iguais do mesmo
 * tipo (mesma restrição do índice UNIQUE da tabela).
 */
#[Title('Categorias do catálogo')]
class CategoryManager extends Component
{
    /**
     * Campos do formulário de nova categoria.
     */
    public string $name = '';

    public string $type = 'service';

    /**
     * Categoria em edição inline (null = nenhuma).
     */
    public ?int $editingId = null;

    public string $editingName = '';

    public function mount(): void
    {
        $this->authorize('catalog.update');
    }

    public function create(): void
    {
        $this->authorize('catalog.update');

        $validated = $this->validate([
            'type' => ['required', Rule::enum(CatalogItemType::class)],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('catalog_categories', 'name')
                    ->where('company_id', $this->companyId())
                    ->where('type', $this->type),
            ],
        ], [
            'name.unique' => 'Já existe uma categoria com este nome para este tipo.',
        ], [
            'name' => 'nome',
            'type' => 'tipo',
        ]);

        // company_id é preenchido automaticamente pelo trait BelongsToCompany.
        CatalogCategory::create([
            'name' => trim($validated['name']),
            'type' => $validated['type'],
        ]);

        $this->reset('name');
        $this->dispatch('notify', type: 'success', message: 'Categoria criada.');
    }

    public function startEditing(int $categoryId): void
    {
        $this->authorize('catalog.update');

        $category = CatalogCategory::query()->findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->editingName = $category->name;
        $this->resetValidation();
    }

    public function cancelEditing(): void
    {
        $this->reset('editingId', 'editingName');
        $this->resetValidation();
    }

    public function rename(): void
    {
        $this->authorize('catalog.update');

        // findOrFail com o escopo da empresa ativa: impede renomear
        // categorias de outra empresa manipulando o id na requisição.
        $category = CatalogCategory::query()->findOrFail($this->editingId);

        $validated = $this->validate([
            'editingName' => [
                'required', 'string', 'max:100',
                Rule::unique('catalog_categories', 'name')
                    ->where('company_id', $this->companyId())
                    ->where('type', $category->type->value)
                    ->ignore($category->id),
            ],
        ], [
            'editingName.unique' => 'Já existe uma categoria com este nome para este tipo.',
        ], [
            'editingName' => 'nome',
        ]);

        $category->update(['name' => trim($validated['editingName'])]);

        $this->cancelEditing();
        $this->dispatch('notify', type: 'success', message: 'Categoria renomeada.');
    }

    /**
     * Só exclui categorias vazias, para não deixar itens "órfãos" sem que o
     * usuário perceba.
     */
    public function delete(int $categoryId): void
    {
        $this->authorize('catalog.update');

        $category = CatalogCategory::query()->findOrFail($categoryId);

        if ($category->items()->exists()) {
            $this->dispatch('notify', type: 'error', message: 'A categoria possui itens vinculados. Mova ou exclua os itens antes de excluí-la.');

            return;
        }

        $category->delete();

        if ($this->editingId === $categoryId) {
            $this->cancelEditing();
        }

        $this->dispatch('notify', type: 'success', message: 'Categoria excluída.');
    }

    public function render(): View
    {
        $categories = CatalogCategory::query()
            ->withCount('items')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (CatalogCategory $category): string => $category->type->value);

        return view('livewire.catalog.category-manager', [
            'categoriesByType' => $categories,
            'typeOptions' => CatalogItemType::options(),
        ]);
    }

    private function companyId(): ?int
    {
        return app(CurrentCompany::class)->id();
    }
}
