<div>
    <x-ui.page-header title="Contratos" subtitle="Contratos de prestação de serviços e suas cobranças recorrentes.">
        @can('create', \App\Models\Contract::class)
            <x-ui.button icon="plus" :href="route('contracts.create')">Novo contrato</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Número, título ou cliente..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Status">
                <option value="">Todos os status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="frequency" class="form-control w-auto" aria-label="Periodicidade">
                <option value="">Todas as periodicidades</option>
                @foreach ($frequencyOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" wire:model.live="expiring" class="rounded text-brand-600"> Término nos próximos 60 dias
            </label>
        </div>

        @if ($contracts->isEmpty())
            <x-ui.empty-state icon="document-check" title="Nenhum contrato encontrado" />
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="number" :$sortField :$sortDirection>Número</x-table.sort-header>
                            <th>Cliente / título</th>
                            <x-table.sort-header field="amount_cents" :$sortField :$sortDirection align="right">Valor</x-table.sort-header>
                            <th>Periodicidade</th>
                            <x-table.sort-header field="start_date" :$sortField :$sortDirection>Início</x-table.sort-header>
                            <x-table.sort-header field="end_date" :$sortField :$sortDirection>Término</x-table.sort-header>
                            <x-table.sort-header field="next_billing_date" :$sortField :$sortDirection>Próx. cobrança</x-table.sort-header>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($contracts as $contract)
                            <tr wire:key="contract-{{ $contract->id }}">
                                <td><a href="{{ route('contracts.show', $contract) }}" class="link tabular-nums">{{ $contract->number }}</a></td>
                                <td class="max-w-xs !whitespace-normal">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $contract->customer->display_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $contract->title }}</p>
                                </td>
                                <td class="text-right tabular-nums">{{ money($contract->amount_cents) }}</td>
                                <td>{{ $contract->billing_frequency->label() }}</td>
                                <td>{{ br_date($contract->start_date) }}</td>
                                <td>{{ $contract->end_date ? br_date($contract->end_date) : 'Indeterminado' }}</td>
                                <td>{{ br_date($contract->next_billing_date) }}</td>
                                <td><x-ui.badge :color="$contract->status->color()">{{ $contract->status->label() }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $contracts->links() }}</div>
        @endif
    </div>
</div>
