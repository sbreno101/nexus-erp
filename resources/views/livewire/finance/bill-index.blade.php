@php
    $isReceivable = $kind === 'receivable';
    $routePrefix = $isReceivable ? 'receivables' : 'payables';
    $modelClass = $isReceivable ? \App\Models\Receivable::class : \App\Models\Payable::class;
@endphp
<div>
    <x-ui.page-header :title="$isReceivable ? 'Contas a receber' : 'Contas a pagar'"
        :subtitle="$isReceivable ? 'Títulos a receber de clientes, avulsos ou gerados pelos contratos.' : 'Despesas e obrigações com fornecedores.'">
        @if (! $isReceivable)
            @can('viewAny', \App\Models\RecurringExpense::class)
                <x-ui.button variant="secondary" icon="arrow-path" :href="route('recurring-expenses.index')">Despesas recorrentes</x-ui.button>
            @endcan
        @endif
        @can('create', $modelClass)
            <x-ui.button icon="plus" :href="route($routePrefix.'.create')">{{ $isReceivable ? 'Nova conta a receber' : 'Nova conta a pagar' }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Total (filtro atual)" :value="money((int) $totals->amount)" :hint="$totals->total_count.' títulos'" icon="document-text" />
        <x-ui.stat :label="$isReceivable ? 'Recebido' : 'Pago'" :value="money((int) $totals->paid)" icon="check-circle" color="green" />
        <x-ui.stat label="Em aberto" :value="money((int) $totals->outstanding)" icon="banknotes" :color="$isReceivable ? 'blue' : 'red'" />
    </div>

    <div class="card relative">
        <x-table.loading />
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search :placeholder="'Descrição, documento ou '.($isReceivable ? 'cliente' : 'fornecedor').'...'" />
            <div class="flex flex-wrap rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                @foreach (['' => 'Todas', 'open' => 'Em aberto', 'overdue' => 'Vencidas', 'upcoming' => 'A vencer', 'paid' => 'Quitadas', 'cancelled' => 'Canceladas'] as $value => $label)
                    <button type="button" wire:click="$set('situation', '{{ $value }}')" @class(['rounded-md px-3 py-1 text-xs font-medium', 'bg-brand-600 text-white' => $situation === $value, 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => $situation !== $value])>{{ $label }}</button>
                @endforeach
            </div>
            <select wire:model.live="category" class="form-control w-auto" aria-label="Categoria">
                <option value="">Todas as categorias</option>
                @foreach ($categories as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="costCenter" class="form-control w-auto" aria-label="Centro de custo">
                <option value="">Todos os centros de custo</option>
                @foreach ($costCenters as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="from" class="form-control w-auto" aria-label="Vencimento a partir de">
            <input type="date" wire:model.live="to" class="form-control w-auto" aria-label="Vencimento até">
        </div>

        @if ($bills->isEmpty())
            <x-ui.empty-state icon="banknotes" title="Nenhum título encontrado" />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="due_date" :$sortField :$sortDirection>Vencimento</x-table.sort-header>
                            <x-table.sort-header field="description" :$sortField :$sortDirection>Descrição</x-table.sort-header>
                            <th>{{ $isReceivable ? 'Cliente' : 'Fornecedor' }}</th>
                            <th>Categoria</th>
                            <x-table.sort-header field="competence" :$sortField :$sortDirection>Competência</x-table.sort-header>
                            <x-table.sort-header field="amount_cents" :$sortField :$sortDirection align="right">Valor</x-table.sort-header>
                            <th class="text-right">Em aberto</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($bills as $bill)
                            <tr wire:key="bill-{{ $bill->id }}">
                                <td @class(['font-semibold text-red-600' => $bill->isOverdue()])>{{ br_date($bill->due_date) }}</td>
                                <td class="min-w-72 max-w-md !whitespace-normal"><a href="{{ route($routePrefix.'.show', $bill) }}" class="link">{{ $bill->description }}</a>
                                    @if ($bill->document_number)<p class="text-xs text-slate-500">{{ $bill->document_number }}</p>@endif
                                </td>
                                <td>{{ $bill->{$partyRelation}?->display_name ?? '—' }}</td>
                                <td>{{ $bill->category?->name ?? '—' }}</td>
                                <td>{{ $bill->competence->format('m/Y') }}</td>
                                <td class="text-right tabular-nums">{{ money($bill->amount_cents) }}</td>
                                <td class="text-right tabular-nums">{{ money($bill->outstandingCents()) }}</td>
                                <td><x-ui.badge :color="$bill->statusColor()">{{ $bill->statusLabel() }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $bills->links() }}</div>
        @endif
    </div>
</div>
