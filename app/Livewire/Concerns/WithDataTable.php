<?php

namespace App\Livewire\Concerns;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Comportamento comum das listagens Livewire: busca, ordenação e paginação.
 *
 * As propriedades com #[Url] ficam na barra de endereços (?busca=...&ordem=...),
 * então o usuário pode recarregar a página ou compartilhar o link com os
 * mesmos filtros.
 *
 * Cada componente define $sortable (colunas permitidas) para impedir que um
 * valor arbitrário vindo da URL seja usado no ORDER BY.
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'ordem', except: '')]
    public string $sortField = '';

    #[Url(as: 'direcao', except: 'asc')]
    public string $sortDirection = 'asc';

    public int $perPage = 15;

    /**
     * Qualquer alteração de filtro volta para a página 1.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['paginators', 'page'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortableColumns(), true)) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset();
        $this->resetPage();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySorting(Builder $query, string $defaultField, string $defaultDirection = 'asc'): Builder
    {
        $field = in_array($this->sortField, $this->sortableColumns(), true) ? $this->sortField : $defaultField;
        $direction = $this->sortField === '' ? $defaultDirection : ($this->sortDirection === 'desc' ? 'desc' : 'asc');

        return $query->orderBy($query->qualifyColumn($field), $direction)->orderBy($query->qualifyColumn('id'), 'desc');
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return property_exists($this, 'sortable') ? $this->sortable : [];
    }

    protected function perPageForCompany(): int
    {
        return app(CurrentCompany::class)->get()?->settingsOrDefault()->items_per_page ?? $this->perPage;
    }
}
