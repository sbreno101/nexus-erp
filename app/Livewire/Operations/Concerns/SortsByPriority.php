<?php

namespace App\Livewire\Operations\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ordenação por prioridade na ordem de importância (Baixa < Média < Alta < Urgente).
 *
 * A coluna "priority" guarda texto ("low", "medium", "high", "urgent").
 * Ordenar o texto em ordem alfabética daria "high, low, medium, urgent",
 * o que não faz sentido para o usuário. Por isso convertemos cada valor em
 * um peso numérico com CASE dentro do ORDER BY.
 *
 * Usado junto com o trait WithDataTable.
 */
trait SortsByPriority
{
    /**
     * Aplica a ordenação da listagem, tratando "priority" de forma especial.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySortingWithPriority(Builder $query, string $defaultField, string $defaultDirection = 'asc'): Builder
    {
        if ($this->sortField !== 'priority' || ! in_array('priority', $this->sortableColumns(), true)) {
            return $this->applySorting($query, $defaultField, $defaultDirection);
        }

        // A direção é validada aqui (só "asc" ou "desc"), pois vai direto no SQL.
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $column = $query->qualifyColumn('priority');

        return $query
            ->orderByRaw("CASE {$column} WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END {$direction}")
            ->orderBy($query->qualifyColumn('id'), 'desc');
    }
}
