<x-layouts::app title="Despesas recorrentes">
    <x-ui.page-header title="Despesas recorrentes" subtitle="Modelos que geram contas a pagar automaticamente (rotina diária)." :back="route('payables.index')">
        @can('create', \App\Models\RecurringExpense::class)
            <x-ui.button icon="plus" :href="route('recurring-expenses.create')">Nova despesa recorrente</x-ui.button>
        @endcan
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($expenses->isEmpty())
            <x-ui.empty-state icon="arrow-path" title="Nenhuma despesa recorrente" description="Cadastre aluguel, assinaturas e outros custos fixos." />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Descrição</th><th>Fornecedor</th><th>Categoria</th><th class="text-right">Valor</th><th>Periodicidade</th><th>Próximo venc.</th><th>Geradas</th><th>Situação</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($expenses as $expense)
                            <tr>
                                <td class="font-medium text-slate-800 dark:text-slate-100">{{ $expense->description }}</td>
                                <td>{{ $expense->supplier?->display_name ?? '—' }}</td>
                                <td>{{ $expense->category?->name ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ money($expense->amount_cents) }}</td>
                                <td>{{ $expense->frequency->label() }} · dia {{ $expense->due_day }}</td>
                                <td>{{ br_date($expense->next_due_date) }}</td>
                                <td>{{ $expense->payables_count }}</td>
                                <td><x-ui.badge :color="$expense->is_active ? 'green' : 'gray'">{{ $expense->is_active ? 'Ativa' : 'Inativa' }}</x-ui.badge></td>
                                <td class="text-right">
                                    @can('update', $expense)
                                        <a href="{{ route('recurring-expenses.edit', $expense) }}" class="rounded p-1.5 text-slate-400 hover:text-slate-700" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())<div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $expenses->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts::app>
