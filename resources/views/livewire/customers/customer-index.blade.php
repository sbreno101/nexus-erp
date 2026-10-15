<div>
    <x-ui.page-header title="Clientes" subtitle="Pessoas físicas e jurídicas atendidas pela empresa.">
        @can('export', \App\Models\Customer::class)
            <x-ui.button variant="secondary" icon="arrow-down-tray" :href="route('customers.export', ['busca' => $search, 'status' => $status])">Exportar CSV</x-ui.button>
        @endcan
        @can('create', \App\Models\Customer::class)
            <x-ui.button icon="plus" :href="route('customers.create')">Novo cliente</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card relative">
        <x-table.loading />

        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <x-table.search placeholder="Buscar por nome, e-mail ou CPF/CNPJ..." />
            <select wire:model.live="status" class="form-control w-auto" aria-label="Filtrar por status">
                <option value="">Todos os status</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="type" class="form-control w-auto" aria-label="Filtrar por tipo">
                <option value="">PF e PJ</option>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @if ($search || $status || $type)
                <button type="button" wire:click="clearFilters" class="text-sm link">Limpar filtros</button>
            @endif
        </div>

        @if ($customers->isEmpty())
            <x-ui.empty-state icon="users" title="Nenhum cliente encontrado" description="Ajuste os filtros ou cadastre um novo cliente.">
                @can('create', \App\Models\Customer::class)
                    <x-ui.button icon="plus" size="sm" :href="route('customers.create')">Cadastrar cliente</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <x-table.sort-header field="name" :$sortField :$sortDirection>Cliente</x-table.sort-header>
                            <x-table.sort-header field="document" :$sortField :$sortDirection>CPF/CNPJ</x-table.sort-header>
                            <th>Contato</th>
                            <th>Responsável</th>
                            <th class="text-center">Contratos ativos</th>
                            <x-table.sort-header field="status" :$sortField :$sortDirection>Status</x-table.sort-header>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($customers as $customer)
                            <tr wire:key="customer-{{ $customer->id }}">
                                <td>
                                    <a href="{{ route('customers.show', $customer) }}" class="link">{{ $customer->display_name }}</a>
                                    @if ($customer->trade_name)
                                        <p class="text-xs text-slate-500">{{ $customer->name }}</p>
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ $customer->formatted_document }}</td>
                                <td>
                                    <p>{{ $customer->email ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $customer->phone ?? $customer->mobile }}</p>
                                </td>
                                <td>{{ $customer->owner?->name ?? '—' }}</td>
                                <td class="text-center">{{ $customer->active_contracts_count }}</td>
                                <td><x-ui.badge :color="$customer->status->color()">{{ $customer->status->label() }}</x-ui.badge></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        @can('update', $customer)
                                            <a href="{{ route('customers.edit', $customer) }}" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Editar"><x-icon name="pencil" class="size-4" /></a>
                                        @endcan
                                        @can('delete', $customer)
                                            <button type="button" wire:click="delete({{ $customer->id }})" wire:confirm="Excluir o cliente {{ $customer->display_name }}?" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Excluir"><x-icon name="trash" class="size-4" /></button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $customers->links() }}</div>
        @endif
    </div>
</div>
