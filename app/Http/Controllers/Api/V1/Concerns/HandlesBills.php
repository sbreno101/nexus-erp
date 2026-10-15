<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros comuns às listagens de contas a receber e a pagar na API.
 *
 * ?situation=open|overdue|upcoming|paid|cancelled ?due_from=AAAA-MM-DD ?due_to=AAAA-MM-DD ?search=
 */
trait HandlesBills
{
    /**
     * @template TModel of \App\Models\Receivable|\App\Models\Payable
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyBillFilters(Builder $query, Request $request): Builder
    {
        $query->search($request->query('search'))
            ->when($request->query('due_from'), fn ($query, $date) => $query->whereDate('due_date', '>=', $date))
            ->when($request->query('due_to'), fn ($query, $date) => $query->whereDate('due_date', '<=', $date));

        match ($request->query('situation')) {
            'open' => $query->whereIn('status', BillStatus::outstanding()),
            'overdue' => $query->overdue(),
            'upcoming' => $query->upcoming(),
            'paid' => $query->where('status', BillStatus::Paid),
            'cancelled' => $query->where('status', BillStatus::Cancelled),
            default => $query,
        };

        return $query;
    }
}
